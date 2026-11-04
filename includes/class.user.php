<?php

/**
 * Длина строки в символах. Работает и без расширения mbstring (CMS не должна требовать лишних расширений).
 */
function textLength($text): int
{
    $text = (string)$text;
    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }
    $n = preg_match_all('/./us', $text); // false, если строка - не валидный UTF-8
    return $n === false ? strlen($text) : $n;
}

enum RegistrationStatus {
    case SUCCESS_WITH_LOGIN;    // Успешная регистрация и вход
    case SUCCESS_WITHOUT_LOGIN; // Успешная регистрация без входа
    case EMAIL_EXISTS;          // Email уже зарегистрирован
    case INVALID_DATA;          // Некорректные данные
    case DATABASE_ERROR;        // Ошибка базы данных
    case UNKNOWN_ERROR;         // Неизвестная ошибка
}

/**
 * Ограничение частоты неудачных попыток входа (защита от подбора пароля).
 * Считает неудачи отдельно для пары "IP + email" и для IP в целом, окно - 15 минут.
 * Данные хранятся в log/login_throttle.json.
 * Примечание: если сайт стоит за прокси/CDN, REMOTE_ADDR - это адрес прокси.
 */
class LoginThrottle
{
    private const WINDOW        = 900; // секунд
    private const MAX_PER_PAIR  = 5;   // неудач для пары IP + email
    private const MAX_PER_IP    = 30;  // неудач с одного IP

    private static function file(): string
    {
        return BOARD_ROOT . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'login_throttle.json';
    }

    private static function keys(string $email): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        return [
            'pair' => hash('sha256', 'p|' . $ip . '|' . strtolower(trim($email))),
            'ip'   => hash('sha256', 'i|' . $ip),
        ];
    }

    /** Выполняет $fn(array &$data) под блокировкой файла */
    private static function withData(callable $fn)
    {
        $file = self::file();
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        protectDirectory($dir);
        $h = @fopen($file, 'c+');
        if ($h === false) {
            return null; // не смогли открыть файл - не блокируем вход
        }
        try {
            flock($h, LOCK_EX);
            $data = json_decode((string)stream_get_contents($h), true);
            if (!is_array($data)) {
                $data = [];
            }
            // Чистим устаревшие записи
            $now = time();
            foreach ($data as $k => $times) {
                $data[$k] = array_values(array_filter((array)$times, fn($t) => $t > $now - self::WINDOW));
                if (!$data[$k]) {
                    unset($data[$k]);
                }
            }
            $result = $fn($data);
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, json_encode($data));
            return $result;
        } finally {
            flock($h, LOCK_UN);
            fclose($h);
        }
    }

    public static function isBlocked(string $email): bool
    {
        $k = self::keys($email);
        return (bool)self::withData(function (array &$d) use ($k) {
            return count($d[$k['pair']] ?? []) >= self::MAX_PER_PAIR
                || count($d[$k['ip']] ?? []) >= self::MAX_PER_IP;
        });
    }

    public static function registerFailure(string $email): void
    {
        $k = self::keys($email);
        self::withData(function (array &$d) use ($k) {
            $d[$k['pair']][] = time();
            $d[$k['ip']][]   = time();
        });
    }

    public static function reset(string $email): void
    {
        $k = self::keys($email);
        self::withData(function (array &$d) use ($k) {
            unset($d[$k['pair']]);
        });
    }
}

class User
{
    /** Хеш-пустышка: проверяем пароль и для несуществующего email, чтобы время ответа не выдавало, есть ли такой пользователь */
    private const DUMMY_HASH = '$2y$10$Bfdn7qskSEpfJHle.EX9kOQVRuQR.qCnRK/9dNcukFqG1ydeANJp6';

    /** Допустимые роли */
    public const ROLES = ['user', 'admin'];

    /** @var BaseTableStorage $storage Хранилище данных (работа с БД) */
    private $storage;
    /** @var string $tableName Название таблицы пользователей в базе данных */
    private $tableName;
    /** @var string $sessionKey Ключ для хранения данных пользователя в сессии */
    private $sessionKey;
    /** @var int|null $sessionLifetime Время жизни сессии в секундах (-1 — бесконечность, 0 — до закрытия браузера) */
    private $sessionLifetime;

    public function __construct(BaseTableStorage $storage,
        string $tableName = 'users',
        string $sessionKey = 'user_data',
        $sessionLifetime = '-1',
        $cookieName = 'LIVE_ID') {
        $this->storage    = $storage;    // Инициализация хранилища данных
        $this->tableName  = $tableName;  // Установка имени таблицы пользователей
        $this->sessionKey = $sessionKey; // Ключ для хранения данных в сессии

                                                                                                                     // Проверка на пустое значение sessionLifetime и установка значения по умолчанию
        $this->sessionLifetime = (isset($sessionLifetime) && $sessionLifetime !== '') ? (int) $sessionLifetime : -1; // Если пусто, то -1 (бесконечность)

        if (! headers_sent() && session_status() === PHP_SESSION_NONE) {
            ini_set('session.name', $cookieName);
            ini_set('session.use_strict_mode', '1');  // не принимаем чужие, выдуманные клиентом идентификаторы сессий
            ini_set('session.use_only_cookies', '1'); // никаких PHPSESSID в адресной строке
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
                'secure'   => !empty($_SERVER['HTTPS']), // Только по HTTPS, если сайт работает по HTTPS
                'httponly' => true,  // Кука недоступна из JavaScript
                'samesite' => 'Lax',
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
            // Нормализуем и проверяем данные
            $email    = strtolower(trim((string)($userData['email'] ?? '')));
            $password = (string)($userData['password'] ?? '');
            $name     = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)($userData['name'] ?? '')));
            $role     = strtolower(trim((string)($userData['role'] ?? 'user')));

            if ($email === '' || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($password) < 8 || strlen($password) > 72 // bcrypt учитывает только первые 72 байта
                || $name === '' || textLength($name) > 100) {
                logMessage('Некорректные данные регистрации', 'ERROR');
                return RegistrationStatus::INVALID_DATA;
            }
            if (!in_array($role, self::ROLES, true)) {
                $role = 'user';
            }

            // Проверяем существование email (точное совпадение без учёта регистра)
            $existing = $this->storage->getRecordsByColumn($this->tableName, 'email', $email, false);
            if (! empty($existing)) {
                logMessage("Email уже зарегистрирован: " . $email, 'ERROR');
                return RegistrationStatus::EMAIL_EXISTS;
            }

            // Сохраняем только известные поля, пароль - в виде хеша
            $record = [
                'email'    => $email,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'name'     => $name,
                'role'     => $role,
            ];

            $success = $this->storage->insertRecord($this->tableName, $record);
            if (! $success) {
                logMessage('Ошибка при сохранении пользователя в базу данных: ' . $email, 'ERROR');
                return RegistrationStatus::DATABASE_ERROR;
            }

            // Автоматически входим, если $autoLogin = true
            if ($autoLogin) {
                // Новый идентификатор сессии при входе (защита от фиксации сессии)
                if (session_status() === PHP_SESSION_ACTIVE && ! headers_sent()) {
                    session_regenerate_id(true);
                }
                $this->setSession($record); // Сохраняем данные в сессию
                logMessage('Пользователь успешно зарегистрирован и вошел в систему: ' . $email, 'INFO');
                return RegistrationStatus::SUCCESS_WITH_LOGIN;
            } else {
                logMessage('Пользователь успешно зарегистрирован: ' . $email, 'INFO');
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
            $email = trim($email);

            // Слишком много неудачных попыток - не проверяем пароль вообще
            if (LoginThrottle::isBlocked($email)) {
                logMessage('Вход временно заблокирован из-за частых неудачных попыток: ' . $email, 'WARNING');
                return false;
            }

            // Получаем пользователей из базы данных по email
            $users = $this->storage->getRecordsByColumn($this->tableName, 'email', $email, false);
            $user  = empty($users) ? null : reset($users);

            // Пароль проверяем всегда (для несуществующего email - по хешу-пустышке), чтобы не было разницы во времени ответа
            $hash = (is_array($user) && isset($user['password'])) ? (string)$user['password'] : self::DUMMY_HASH;
            $ok   = password_verify($password, $hash) && $user !== null;

            if ($ok) {
                // Регенерируем идентификатор сессии для защиты от фиксации сессии
                session_regenerate_id(true);

                // Устанавливаем данные пользователя в сессию
                $this->setSession($user);
                LoginThrottle::reset($email);
                return true;
            }

            LoginThrottle::registerFailure($email);
            logMessage('Неудачная попытка входа: ' . $email, 'WARNING');
            return false;
        } catch (Exception $e) {
            // Логируем ошибку
            logMessage('Ошибка при авторизации: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Заблокирован ли вход для этой пары IP + email (используется для понятного сообщения в интерфейсе).
     */
    public function isLoginBlocked(string $email): bool
    {
        return LoginThrottle::isBlocked(trim($email));
    }

    /**
     * Выход пользователя.
     */
    public function logout(): void
    {
        if (isset($_SESSION[$this->sessionKey])) {
            unset($_SESSION[$this->sessionKey]);
            // После выхода выдаём новый идентификатор сессии и новый CSRF-токен
            if (session_status() === PHP_SESSION_ACTIVE && ! headers_sent()) {
                session_regenerate_id(true);
            }
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
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
     * Проверяет, что текущий пользователь - администратор, по ДАННЫМ ХРАНИЛИЩА, а не по роли, сохранённой в сессии.
     * Так удалённый или разжалованный администратор теряет доступ сразу, а не после выхода.
     * Для действий, меняющих данные, использовать этот метод, а не isCurrentRole().
     */
    public function isAdmin(): bool
    {
        $current = $_SESSION[$this->sessionKey] ?? null;
        if (! is_array($current) || empty($current['email']) || strtolower((string)($current['role'] ?? '')) !== 'admin') {
            return false;
        }
        try {
            $users = $this->storage->getRecordsByColumn($this->tableName, 'email', (string)$current['email'], false);
        } catch (Exception $e) {
            return false;
        }
        $record = empty($users) ? null : reset($users);
        return is_array($record) && strtolower((string)($record['role'] ?? '')) === 'admin';
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
