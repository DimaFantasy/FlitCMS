<?php

/**
 * Класс User:
 * 1. __construct($storage, $tableName, $sessionKey, $sessionLifetime, $cookieName) - Конструктор класса, инициализация параметров.
 * 2. register($userData, $autoLogin) - Регистрация пользователя.
 * 3. login($email, $password) - Авторизация пользователя.
 * 4. logout() - Выход пользователя.
 * 5. isLoggedIn() - Проверка авторизации.
 * 6. setSession($userData) - Установка данных в сессию.
 * 7. getCurrentUser() - Получение данных текущего пользователя.
 * 8. getCurrentUserRole() - Получение роли текущего пользователя.
 * 9. isCurrentRole($role) - Проверка роли текущего пользователя.
 * 10. getUserByEmail($email) - Получение данных пользователя по email.
 * 11. getAllUsers() - Получение списка всех пользователей.
 * 12. deleteUser($email) - Удаление пользователя по email.
 * 13. updateRecord($conditions, $dataUpdate) - Обновление записей пользователей.
 */

enum RegistrationStatus
{
    case SUCCESS_WITH_LOGIN;    // Успешная регистрация и вход
    case SUCCESS_WITHOUT_LOGIN; // Успешная регистрация без входа
    case EMAIL_EXISTS;          // Email уже зарегистрирован
    case INVALID_DATA;          // Некорректные данные
    case DATABASE_ERROR;        // Ошибка базы данных
    case UNKNOWN_ERROR;         // Неизвестная ошибка
}

class User
{
    /** @var BaseTableStorage $storage Хранилище данных (работа с БД) */
    private $storage;
    /** @var string $tableName Название таблицы пользователей в базе данных */
    private $tableName;
    /** @var string $sessionKey Ключ для хранения данных пользователя в сессии */
    private $sessionKey;
    /** @var int|null $sessionLifetime Время жизни сессии в секундах (-1 — бесконечность, 0 — до закрытия браузера) */
    private $sessionLifetime;

    public function __construct(
        BaseTableStorage $storage,
        string $tableName = 'users',
        string $sessionKey = 'user_data',
        $sessionLifetime = '-1',
        $cookieName = 'LIVE_ID'
    ) {
        $this->storage    = $storage;    // Инициализация хранилища данных
        $this->tableName  = $tableName;  // Установка имени таблицы пользователей
        $this->sessionKey = $sessionKey; // Ключ для хранения данных в сессии

        // Проверка на пустое значение sessionLifetime и установка значения по умолчанию
        $this->sessionLifetime = (isset($sessionLifetime) && $sessionLifetime !== '') ? (int) $sessionLifetime : -1; // Если пусто, то -1 (бесконечность)

        if (! headers_sent() && session_status() === PHP_SESSION_NONE) {
            ini_set('session.name', $cookieName);
            if ($this->sessionLifetime === -1) {
                $CookieLifetime = 10 * 365 * 24 * 3600; // 10 лет в секундах
            } elseif ($this->sessionLifetime === 0) {
                $CookieLifetime = 0; // До закрытия браузера
            } else {
                $CookieLifetime = $this->sessionLifetime * 3600; // В часах
            }
            // Устанавливаем параметры куки сессии
            $cookieParams = [
                'lifetime' => $CookieLifetime, // Время жизни в секундах
                'path'     => '/',
                'domain'   => '',
                'secure'   => false, // Отключено
                'httponly' => false, // Отключено
            ];
            session_set_cookie_params($cookieParams);
            session_start();
            // logMessage('Сессия успешно запущена.', 'INFO');
        } else {
            logMessage('Сессия уже запущена или заголовки уже отправлены.', 'WARNING');
        }

        // Создаём таблицу пользователей, если её нет.
        $columns = [
            'email'    => 'VARCHAR(255) NOT NULL',
            'password' => 'VARCHAR(255) NOT NULL',
            'name'     => 'VARCHAR(255)',
            'role'     => 'VARCHAR(50)',
        ];

        if ($this->storage->createTable($this->tableName, $columns)) {
            // logMessage('Таблица пользователей успешно создана или уже существует.', 'INFO');
        } else {
            logMessage('Ошибка при создании таблицы пользователей.', 'ERROR');
        }

        // Генерация CSRF-токена, если он еще не создан
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            // logMessage('CSRF-токен успешно сгенерирован.', 'INFO');
        }
    }

    /**
     * Регистрация пользователя.
     *
     * @param array $userData Данные пользователя (email, password и т.д.).
     * @param bool $autoLogin Автоматически войти после регистрации (по умолчанию true).
     * @return RegistrationStatus Статус регистрации.
     */
    public function register(array $userData, bool $autoLogin = true): RegistrationStatus
    {
        try {
            // Проверяем корректность данных
            if (empty($userData['email']) || empty($userData['password'])) {
                logMessage('Некорректные данные: email или пароль отсутствуют', 'ERROR');
                return RegistrationStatus::INVALID_DATA;
            }

            // Проверяем существование email
            $existing = $this->storage->getRecordsByColumn($this->tableName, 'email', $userData['email'], false);
            if (! empty($existing)) {
                logMessage("Email уже зарегистрирован: " . $userData['email'], 'ERROR');
                return RegistrationStatus::EMAIL_EXISTS;
            }

            // Хешируем пароль
            $userData['password'] = password_hash($userData['password'], PASSWORD_BCRYPT);

            // Сохраняем пользователя
            $success = $this->storage->insertRecord($this->tableName, $userData);
            if (! $success) {
                logMessage('Ошибка при сохранении пользователя в базу данных: ' . $userData['email'], 'ERROR');
                return RegistrationStatus::DATABASE_ERROR;
            }

            // Автоматически входим, если $autoLogin = true
            if ($autoLogin) {
                $this->setSession($userData); // Сохраняем данные в сессию
                logMessage('Пользователь успешно зарегистрирован и вошел в систему: ' . $userData['email'], 'INFO');
                return RegistrationStatus::SUCCESS_WITH_LOGIN;
            } else {
                logMessage('Пользователь успешно зарегистрирован: ' . $userData['email'], 'INFO');
                return RegistrationStatus::SUCCESS_WITHOUT_LOGIN;
            }
        } catch (Exception $e) {
            logMessage('Неизвестная ошибка при регистрации: ' . $e->getMessage(), 'ERROR');
            return RegistrationStatus::UNKNOWN_ERROR;
        }
    }

    /**
     * Авторизация пользователя.
     */
    public function login(string $email, string $password): bool
    {
        try {
            // Получаем пользователей из базы данных по email
            $users = $this->storage->getRecordsByColumn($this->tableName, 'email', $email, false);

            // Если пользователь с таким email не найден, возвращаем false
            if (empty($users)) {
                logMessage('Пользователь с email ' . $email . ' не найден.', 'WARNING');
                return false;
            }

            // Берем первого пользователя из массива (предполагаем, что email уникален)
            $user = reset($users); // Берем первого пользователя из массива

            // Проверяем, совпадает ли введенный пароль с хешем пароля из базы данных
            if (password_verify($password, $user['password'])) {
                // Регенерируем идентификатор сессии для защиты от фиксации сессии
                session_regenerate_id(true);

                // Устанавливаем данные пользователя в сессию
                $this->setSession($user);

                // Логируем успешную авторизацию
                // logMessage('Пользователь успешно авторизован: ' . $email, 'INFO');
                return true;
            } else {
                // Логируем попытку ввода неверного пароля
                logMessage('Неверный пароль для пользователя: ' . $email, 'WARNING');
                return false;
            }
        } catch (Exception $e) {
            // Логируем ошибку
            logMessage('Ошибка при авторизации: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Выход пользователя.
     */
    public function logout(): void
    {
        if (isset($_SESSION[$this->sessionKey])) {
            unset($_SESSION[$this->sessionKey]);
            // logMessage('Пользователь вышел из системы.', 'INFO');
        } else {
            logMessage('Попытка выхода неавторизованного пользователя.', 'WARNING');
        }
    }

    /**
     * Проверка авторизации.
     */
    public function isLoggedIn(): bool
    {
        $isLoggedIn = ! empty($_SESSION[$this->sessionKey]);
        // logMessage('Проверка авторизации: ' . ($isLoggedIn ? 'Пользователь авторизован' : 'Пользователь не авторизован'), 'INFO');
        return $isLoggedIn;
    }

    /**
     * Установка данных в сессию.
     */
    private function setSession(array $userData): void
    {
        $_SESSION[$this->sessionKey] = [
            'email' => $userData['email'],
            'name'  => $userData['name'],
            'role'  => $userData['role'], // Добавляем роль
        ];

        if ($this->sessionLifetime === -1) {
            // "Вечная" кука: 10 лет от текущего момента
            $expiration = time() + 10 * 365 * 24 * 3600; // 10 лет в секундах
        } elseif ($this->sessionLifetime === 0) {
            // Сессионная кука: удаляется при закрытии браузера
            $expiration = 0;
        } else {
            // Время жизни в часах
            $expiration = time() + $this->sessionLifetime * 3600; // Переводим часы в секунды
        }

        setcookie(
            session_name(),
            session_id(),
            [
                'expires'  => $expiration,
                'path'     => '/',
                'domain'   => '',
                'secure'   => false,
                'httponly' => false,
            ]
        );
        // logMessage('Данные пользователя установлены в сессию: ' . $userData['email'], 'INFO');
    }

    /**
     * Получение данных текущего пользователя.
     */
    public function getCurrentUser(): ?array
    {
        $userData = $_SESSION[$this->sessionKey] ?? null;
        // logMessage('Получение данных текущего пользователя: ' . ($userData ? 'Данные получены' : 'Данные отсутствуют'), 'INFO');
        return $userData;
    }

    /**
     * Получение роли текущего пользователя.
     *
     * Эта функция извлекает данные текущего пользователя из сессии и возвращает его роль.
     * Если роль не найдена, возвращается null.
     *
     * @return string|null Роль пользователя, либо null, если роль не найдена.
     */
    public function getCurrentUserRole(): ?string
    {
        $userData = $_SESSION[$this->sessionKey] ?? null;
        return $userData['role'] ?? null;
    }

    /**
     * Проверка роли текущего пользователя.
     *
     * Эта функция проверяет, соответствует ли роль текущего пользователя переданному значению.
     * Сравнение происходит без учета регистра.
     *
     * @param string $role Роль для сравнения.
     * @return bool Возвращает true, если роль совпадает, иначе false.
     */
    public function isCurrentRole($role): bool
    {
        $userData = $_SESSION[$this->sessionKey] ?? null;
        if (! $userData || ! isset($userData['role'])) {
            return false;
        }

        // Сравниваем роль без учета регистра
        return strtolower($userData['role']) === strtolower($role);
    }

    /**
     * Получает данные пользователя по email.
     *
     * @param string $email Email пользователя.
     * @return array|null Возвращает массив с данными пользователя или null, если пользователь не найден.
     */
    public function getUserByEmail(string $email): ?array
    {
        try {
            $users = $this->storage->getRecordsByColumn($this->tableName, 'email', $email, false);

            if (! empty($users)) {
                logMessage('Данные пользователя успешно получены: ' . $email, 'INFO');
                return reset($users); // Возвращаем первого пользователя
            } else {
                logMessage('Пользователь с email ' . $email . ' не найден.', 'WARNING');
                return null;
            }
        } catch (Exception $e) {
            logMessage('Ошибка при получении данных пользователя: ' . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Получает список всех пользователей из базы данных.
     *
     * @return array Массив пользователей или пустой массив, если пользователей нет.
     */
    public function getAllUsers(): array
    {
        try {
            // Получаем все записи из таблицы пользователей
            $users = $this->storage->getAllRecords($this->tableName);

            // Логируем успешное получение данных
            // logMessage('Список всех пользователей успешно получен.', 'INFO');

            return $users;
        } catch (Exception $e) {
            // Логируем ошибку
            logMessage('Ошибка при получении списка пользователей: ' . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Удаляет пользователя по email.
     *
     * @param string $email Email пользователя для удаления.
     * @return bool Возвращает true, если пользователь успешно удален, иначе false.
     */
    public function deleteUser(string $email): bool
    {
        try {
            // Удаляем пользователя из базы данных по email
            $success = $this->storage->deleteRecord($this->tableName, ['email' => $email]);

            if ($success) {
                logMessage("Пользователь успешно удален: " . $email, 'INFO');
                return true;
            } else {
                logMessage("Ошибка при удалении пользователя: " . $email, 'ERROR');
                return false;
            }
        } catch (Exception $e) {
            logMessage('Ошибка при удалении пользователя: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Обновляет записи пользователей в базе данных по заданным условиям.
     *
     * @param array $conditions Условия для поиска записей (например, ['email' => 'user@example.com']).
     * @param array $dataUpdate Данные для обновления (например, ['name' => 'Новое имя']).
     * @return int Количество обновленных записей.
     */
    public function updateRecord(array $conditions, array $dataUpdate): int
    {
        try {
            // Проверяем, пытается ли пользователь обновить пароль
            if (isset($dataUpdate['password'])) {
                // Хешируем новый пароль перед обновлением
                $dataUpdate['password'] = password_hash($dataUpdate['password'], PASSWORD_BCRYPT);
                logMessage('Пароль пользователя хеширован перед обновлением.', 'INFO');
            }

            // Вызываем метод updateRecord хранилища
            $updatedCount = $this->storage->updateRecord($this->tableName, $conditions, $dataUpdate);

            if ($updatedCount > 0) {
                logMessage("Обновлено $updatedCount записей пользователей.", 'INFO');
            } else {
                logMessage("Не найдено записей для обновления.", 'WARNING');
            }

            return $updatedCount;
        } catch (Exception $e) {
            logMessage('Ошибка при обновлении пользователя: ' . $e->getMessage(), 'ERROR');
            return 0;
        }
    }
}
