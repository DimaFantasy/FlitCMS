<?php
// Защита от прямого доступа
define('BOARD', true);
define('BOARD_ROOT', __DIR__);

header('X-Content-Type-Options: nosniff');

// Подключение зависимостей
require_once 'includes/class.logger.php';
require_once 'includes/class.user.php';
require_once 'includes/class.template.php';
require_once 'includes/class.database.php';
require_once 'includes/class.translator.php';
// Загрузка конфигурации
$cmsConfig = require 'config/config.inc.php';

// Определяем язык из куки, используя имя куки из конфигурации (по умолчанию 'LANG')
$lang = $_COOKIE[$cmsConfig['langCookieName']] ?? $_POST['lang'] ?? $cmsConfig['defaultLanguage'];
Translator::configure(
    $cmsConfig['langDir'], // Директория с языковыми файлами
    $cmsConfig['missingTranslationsFileName'], // Файл для отсутствующих переводов
    $cmsConfig['validLanguages'], // Список допустимых языков
    $lang  // Язык
);

/**
 * Функция перевода, возвращает значение
 * @param string $key Ключ перевода
 * @param string|null $lang Код языка
 * @param bool $trackMissing Добавлять ли отсутствующий перевод в список (по умолчанию true)
 * @return string Переведенный текст или ключ в квадратных скобках
 */
function t($key, $lang = null, $trackMissing = true)
{
    return Translator::translate($key, $lang, $trackMissing);
}

/**
 * Ответ "сервис недоступен": без подробностей об ошибке (они остаются в журнале)
 */
function respondUnavailable()
{
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => t('Service temporarily unavailable')]);
    exit;
}

// Определяем тип хранилища
$storageType = $cmsConfig['storage'];
$storage = null;
if ($storageType === 'mysql') {
    try {
        // Подключаемся к MySQL
        $storage = new MysqlTableStorage($cmsConfig);
        $user = new User(
            $storage, // Объект хранилища (MysqlTableStorage, JsonTableStorage)
            $cmsConfig['mysql']['UsersTableName'], // Название таблицы пользователей  в случае с Json  путь к имени файла
            $cmsConfig['sessionKey'], // Ключ сессии
            // Здесь нужно добавить время жизни сессии
            $cmsConfig['lifeTime'], // Время жизни сессии, например, 1 для 1 часа
            $cmsConfig['userCookeName']
        );
        $dataStorage = new MysqlTableStorage($cmsConfig);
    } catch (Throwable $e) {
        logMessage("Ошибка подключения к базе данных: " . $e->getMessage(), 'ERROR');
        respondUnavailable();
    }
} elseif ($storageType === 'json') {
    // Используем JSON-файл для хранения пользователей
    $storage = new JsonTableStorage($cmsConfig['json']);
    $user = new User(
        $storage, // Объект хранилища (MysqlTableStorage, JsonTableStorage
        $cmsConfig['json']['UsersTableName'], // Название таблицы пользователей  в случае с Json  путь к имени файла
        $cmsConfig['sessionKey'], // Ключ сессии
        // Здесь нужно добавить время жизни сессии
        $cmsConfig['lifeTime'], // Время жизни сессии, например, 1 для 1 часа
        $cmsConfig['userCookeName']
    );
    $dataStorage = new JsonTableStorage($cmsConfig['json']);
} else {
    logMessage("Неизвестный тип хранилища: $storageType", 'ERROR');
    respondUnavailable();
}

/**
 * Обработка AJAX-запросов и действий пользователя
 * 
 * @param string $action   Название действия (register_user, login, get_user и т.д.)
 * @param User   $user     Объект пользователя для работы с авторизацией
 * @param mixed  $storage  Объект хранилища данных (MySQL или JSON)
 * @return void           Выводит JSON-ответ и завершает выполнение скрипта
 */
function handleAction($action, $user, $storage)
{
    $response = ['success' => false, 'message' => t('Unknown action')];

    // Действия, доступные без входа. Все остальные - только для администратора.
    $publicActions = ['login', 'register_user', 'sendMessage', 'get_valid_languages'];
    if (!in_array($action, $publicActions, true) && !$user->isAdmin()) { // проверка по хранилищу, а не по роли в сессии
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => t('Access denied')]);
        exit;
    }

    try {
        switch ($action) {
            // Регистрация нового пользователя
            case 'register_user':
                $response = registerUser($user);
                break;

            // Добавление пользователя из админки (только администратор, роль задаётся в форме)
            case 'admin_add_user':
                $response = registerUser($user, true);
                break;

            // Проверка, что текущий пользователь всё ещё администратор (страница админки опрашивает её)
            case 'ping':
                $response = ['success' => true];
                break;

            // Получение данных пользователя
            case 'get_user':
                $response = getUser($user);
                break;

            // Удаление пользователя
            case 'delete_user':
                $response = deleteUser($user);
                break;

            // Обновление данных пользователя
            case 'update_user':
                $response = updateUser($user);
                break;

            // Сброс пароля пользователя
            case 'reset_password':
                $response = resetPassword($user);
                break;

            // Авторизация пользователя
            case 'login':
                $response = loginUser($user);
                break;

            // Отправка сообщения
            case 'sendMessage':
                $response = sendMessage($storage);
                break;

            // Очистка журнала логов
            case 'clearLog':
                $response = clearLog();
                break;

            // Обновление настроек системы
            case 'update_settings':
                $response = updateSettings();
                break;

            // Получение списка всех пользователей
            case 'get_all_users':
                $response = getAllUsers($user);
                break;


            // Получение списка отсутствующих переводов
            case 'get_missing_translations':
                $response = getMissingTranslations();
                break;

            // Получения списка доступных языков
            case 'get_valid_languages':
                $response = getValidLanguages();
                break;

            // Получение перевода по ключу
            case 'get_translation':
                $response = getTranslation();
                break;

            // Удаление отсутствующего перевода
            case 'delete_missing_translation':
                $response = deleteMissingTranslation();
                break;

            // Сохранение перевода
            case 'save_translation':
                $response = saveTranslation();
                break;

            // Удаление перевода ключа для одного языка
            case 'delete_translation':
                $response = deleteTranslation();
                break;

            // Поиск оригинального ключа по переводу
            case 'find_original_key':
                $response = findOriginalKey();
                break;

            case 'clear_cache':
                $response = clearCache();
                break;
        }
    } catch (Throwable $e) {
        logMessage('Ошибка в действии ' . $action . ': ' . $e->getMessage(), 'ERROR');
        // Пользователю показываем только текст "своих" исключений CMS (Exception с переведённым сообщением).
        // Ошибки БД и PHP (PDOException, TypeError и т.п.) остаются в журнале.
        $response = [
            'success' => false,
            'message' => get_class($e) === 'Exception' ? $e->getMessage() : t('Unknown error occurred'),
        ];
    }

    echo json_encode($response);
    exit;
}

// Функция для регистрации пользователя
/**
 * Убирает из данных пользователя всё, что нельзя отдавать клиенту (хеш пароля)
 */
function publicUserData($userData)
{
    if (is_array($userData)) {
        unset($userData['password']);
    }
    return $userData;
}

/**
 * true, если $email - единственный администратор в системе
 */
function isLastAdmin($user, $email)
{
    $admins = array_filter($user->getAllUsers(), function ($u) {
        return strtolower((string)($u['role'] ?? '')) === 'admin';
    });
    if (count($admins) !== 1) {
        return false;
    }
    $only = reset($admins);
    return strtolower((string)($only['email'] ?? '')) === strtolower((string)$email);
}

function registerUser($user, $adminMode = false)
{
    // Публичная регистрация ВСЕГДА создаёт обычного пользователя. Роль принимает только действие
    // admin_add_user, и только если запрос действительно пришёл от администратора (проверка по хранилищу).
    if ($adminMode && !$user->isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => t('Access denied')]);
        exit;
    }

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $name = trim((string)($_POST['name'] ?? ''));
    $autoLogin = isset($_POST['autoLogin']) ? filter_var($_POST['autoLogin'], FILTER_VALIDATE_BOOLEAN) : true;

    // Роль может задать ТОЛЬКО администратор через admin_add_user (форма "Добавить пользователя" в админке).
    // В публичной регистрации роль из запроса игнорируется: иначе любой посетитель мог бы стать администратором.
    // Первого администратора создаём через create-admin.php (см. README)
    $role = 'user';
    if ($adminMode) {
        $requested = strtolower(trim((string)($_POST['role'] ?? 'user')));
        $role = in_array($requested, User::ROLES, true) ? $requested : 'user';
        $autoLogin = false; // администратор не должен "переключаться" на созданного пользователя
    } elseif ($user->isAdmin()) {
        $autoLogin = false; // вошедший администратор не должен "переключаться" на нового пользователя
    }

    if ($email === '' || $password === '' || $name === '') {
        throw new Exception(t('Please fill in all fields'));
    }
    if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception(t('Invalid email address'));
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        throw new Exception(t('Password must be between 8 and 72 characters'));
    }
    if (textLength($name) > 100) {
        throw new Exception(t('Value is too long'));
    }

    $registrationStatus = $user->register([
        'email' => $email,
        'password' => $password,
        'name' => $name,
        'role' => $role,
    ], $autoLogin);

    switch ($registrationStatus) {
        case RegistrationStatus::SUCCESS_WITH_LOGIN:
            return ['success' => true, 'message' => t('Registration successful! You are now logged in.')];
        case RegistrationStatus::SUCCESS_WITHOUT_LOGIN:
            return ['success' => true, 'message' => t('Registration successful!')];
        case RegistrationStatus::EMAIL_EXISTS:
            throw new Exception(t('Email already registered'));
        case RegistrationStatus::INVALID_DATA:
            throw new Exception(t('Invalid data provided'));
        case RegistrationStatus::DATABASE_ERROR:
            throw new Exception(t('Database error occurred'));
        case RegistrationStatus::UNKNOWN_ERROR:
            throw new Exception(t('Unknown error occurred'));
        default:
            throw new Exception(t('Registration error'));
    }
}

// Функция для получения пользователя
function getUser($user)
{
    $email = trim((string)($_POST['email'] ?? ''));
    if (empty($email)) {
        throw new Exception(t('Email is not specified'));
    }

    $found = $user->getUserByEmail($email);
    if ($found) {
        // Хеш пароля клиенту не отдаём
        return ['success' => true, 'user' => publicUserData($found)];
    } else {
        return ['success' => false, 'message' => t('User not found')];
    }
}

// Функция для удаления пользователя
function deleteUser($user)
{
    $email = trim((string)($_POST['email'] ?? ''));

    if (empty($email)) {
        throw new Exception(t('Email is not specified'));
    }

    // Нельзя удалить последнего администратора - иначе доступ к админке будет потерян
    if (isLastAdmin($user, $email)) {
        throw new Exception(t('Cannot remove or demote the last administrator'));
    }

    if ($user->deleteUser($email)) {
        return ['success' => true, 'message' => t('User successfully deleted')];
    } else {
        throw new Exception(t('Error deleting user'));
    }
}

// Функция для обновления пользователя
function updateUser($user)
{
    $data = json_decode((string)($_POST['data'] ?? ''), true); // Условие для поиска записи
    $dataUpdate = json_decode((string)($_POST['dataUpdate'] ?? ''), true); // Данные для обновления

    // Убедиться, что json_decode() не возвращает null
    if (!is_array($data) || !is_array($dataUpdate)) {
        // Ошибка в декодировании JSON
        return ['success' => false, 'message' => t('Invalid input data')];
    }

    // Проверка наличия обязательных полей
    if (!isset($data['email']) || !is_string($data['email']) || !isset($dataUpdate['name']) || !isset($dataUpdate['role'])) {
        // Ошибка в отсутствии обязательных полей
        return ['success' => false, 'message' => t('Missing required fields')];
    }

    $email = $data['email'];
    $name = trim((string)$dataUpdate['name']);
    $role = strtolower(trim((string)$dataUpdate['role']));

    if ($name === '' || textLength($name) > 100) {
        return ['success' => false, 'message' => t('Missing required fields')];
    }
    if (!in_array($role, User::ROLES, true)) {
        return ['success' => false, 'message' => t('Invalid data provided')];
    }
    // Нельзя разжаловать последнего администратора
    if ($role !== 'admin' && isLastAdmin($user, $email)) {
        throw new Exception(t('Cannot remove or demote the last administrator'));
    }

    // В хранилище уходят ТОЛЬКО email (условие) и name/role (изменения): любые другие поля из запроса игнорируются
    $updatedCount = $user->updateRecord(['email' => $email], ['name' => $name, 'role' => $role]);
    if ($updatedCount > 0) {
        return ['success' => true, 'message' => t('User updated successfully')];
    } else {
        return ['success' => false, 'message' => t('Failed to update user')];
    }
}

// Функция для сброса пароля
function resetPassword($user)
{
    $email = trim((string)($_POST['email'] ?? ''));
    $newPassword = (string)($_POST['newPassword'] ?? '');

    if (empty($email) || empty($newPassword)) {
        throw new Exception(t('Email and new password are required'));
    }
    if (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
        throw new Exception(t('Password must be between 8 and 72 characters'));
    }

    // Обновляем пароль (метод updateRecord сам хеширует пароль)
    $success = $user->updateRecord(
        ['email' => $email],
        ['password' => $newPassword] // Пароль передается в открытом виде
    );

    if ($success) {
        return ['success' => true, 'message' => t('Password reset successfully')];
    } else {
        throw new Exception(t('Error resetting password'));
    }
}

// Функция для входа пользователя
function loginUser($user)
{
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        throw new Exception(t('Please fill in all fields'));
    }

    // Защита от подбора пароля: после серии неудач вход временно закрыт
    if ($user->isLoginBlocked($email)) {
        http_response_code(429);
        throw new Exception(t('Too many login attempts. Try again later.'));
    }

    if ($user->login($email, $password)) {
        return ['success' => true, 'message' => t('Login successful!')];
    } else {
        throw new Exception(t('Invalid email or password'));
    }
}

// Функция для отправки сообщения
function sendMessage($storage)
{
    $email = trim((string)($_POST['email'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if (empty($email) || empty($message)) {
        throw new Exception(t('Please fill in all fields'));
    }
    if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception(t('Invalid email address'));
    }
    if (textLength($message) > 1000) { // столько помещается в колонку VARCHAR(1000)
        throw new Exception(t('Value is too long'));
    }

    $data = [
        'created_at' => date('Y-m-d H:i:s'),
        'email' => $email,
        'message' => $message
    ];

    $columns = [
        'email' => 'VARCHAR(255) NOT NULL',
        'message' => 'VARCHAR(1000) NOT NULL',
        'created_at' => 'VARCHAR(255)'
    ];

    $storage->createTable('messages', $columns);
    $storage->insertRecord('messages', $data);

    return ['success' => true, 'message' => t('Message sent!')];
}

// Функция для очистки логов
function clearLog()
{
    Logger::getInstance()->logClear();
    return ['success' => true, 'message' => t('Logs cleared!')];
}

// Функция для обновления настроек
function updateSettings()
{
    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? '';

    if (empty($key) || !isset($value)) {
        throw new Exception(t('Key and value are required'));
    }

    // Ключ: только буквы, цифры, _ и разделитель ## (он попадает в регулярное выражение)
    if (!preg_match('/^[A-Za-z0-9_]+(##[A-Za-z0-9_]+)*$/', $key)) {
        throw new Exception(t('Invalid key'));
    }
    // Значение подставляется в PHP-файл в одинарных кавычках: убираем переводы строк и экранируем \ и '
    $value = str_replace(["\r", "\n"], '', (string)$value);
    if ($value !== 'true' && $value !== 'false') {
        $value = str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    // Путь к файлу конфигурации
    $configFile = 'config/config.inc.php';

    // Чтение файла построчно
    $lines = file($configFile);
    if ($lines === false) {
        throw new Exception(t('Failed to read config file'));
    }

    $updated = false;
    $newLines = [];
    $keyParts = explode('##', $key); // Разделяем ключ на части с использованием ##
    $currentDepth = 0; // Текущий уровень вложенности
    $inArray = false; // Флаг, указывающий, что мы находимся внутри массива
    $arrayStack = []; // Стек для отслеживания вложенных массивов

    foreach ($lines as $line) {
        // Если ключ без вложенности (например, 'version')
        if (count($keyParts) === 1) {
            // Проверяем, соответствует ли текущая строка ключу
            if (preg_match("/^\s*'$keyParts[0]'\s*=>\s*(.*?)(\s*,\s*)?(\/\/.*)?$/", $line, $matches)) {
                // Определяем тип значения
                if ($value === 'true' || $value === 'false') {
                    // Если значение строка 'true' или 'false', преобразуем в булевое
                    $newValue = $value === 'true' ? 'true' : 'false';
                } elseif (is_bool($value)) {
                    // Если значение уже булевое, оставляем как есть
                    $newValue = $value ? 'true' : 'false';
                } else {
                    // Для остальных значений добавляем кавычки
                    $newValue = "'$value'";
                }

                // Сохраняем комментарий, если он есть
                $comment = isset($matches[3]) ? $matches[3] : '';

                // Обновление строки с переменной, сохраняя комментарий
                $newLine = rtrim("    '$keyParts[0]' => $newValue," . ($comment ? " $comment" : "")) . PHP_EOL;
                $newLines[] = $newLine;
                $updated = true;
            } else {
                // Сохраняем строку без изменений
                $newLines[] = $line;
            }
        } else {
            // Если ключ с вложенностью (например, 'mysql##host')
            if ($inArray && $currentDepth < count($keyParts)) {
                // Проверяем, соответствует ли текущая строка следующей части ключа
                if (preg_match("/^\s*'" . $keyParts[$currentDepth] . "'\s*=>\s*(.*?)(\s*,\s*)?(\/\/.*)?$/", $line, $matches)) {
                    // Если это последняя часть ключа, обновляем значение
                    if ($currentDepth === count($keyParts) - 1) {
                        // Определяем тип значения
                        if ($value === 'true' || $value === 'false') {
                            // Если значение строка 'true' или 'false', преобразуем в булевое
                            $newValue = $value === 'true' ? 'true' : 'false';
                        } elseif (is_bool($value)) {
                            // Если значение уже булевое, оставляем как есть
                            $newValue = $value ? 'true' : 'false';
                        } else {
                            // Для остальных значений добавляем кавычки
                            $newValue = "'$value'";
                        }

                        // Сохраняем комментарий, если он есть
                        $comment = isset($matches[3]) ? $matches[3] : '';

                        // Обновление строки с переменной, сохраняя комментарий
                        $newLine = rtrim(str_repeat('    ', $currentDepth + 1) . "'" . $keyParts[$currentDepth] . "' => $newValue," . ($comment ? " $comment" : "")) . PHP_EOL;
                        $newLines[] = $newLine;
                        $updated = true;
                        $inArray = false; // Выходим из массива после обновления
                    } else {
                        // Если это не последняя часть ключа, продолжаем углубляться
                        $newLines[] = $line;
                        $currentDepth++;
                    }
                } else {
                    // Сохраняем строку без изменений
                    $newLines[] = $line;
                }
            } else {
                // Поиск начала массива для текущей части ключа
                if (preg_match("/^\s*'" . $keyParts[$currentDepth] . "'\s*=>\s*\[/", $line)) {
                    // Нашли начало массива (например, 'mysql' => [)
                    $newLines[] = $line;
                    $inArray = true; // Устанавливаем флаг, что мы внутри массива
                    $currentDepth++;
                } else {
                    // Сохраняем строку без изменений
                    $newLines[] = $line;
                }
            }
        }
    }

    if (!$updated) {
        throw new Exception(t('Key not found in config file'));
    }

    // Запись обновленного содержимого в файл
    if (file_put_contents($configFile, implode('', $newLines), LOCK_EX) === false) {
        throw new Exception(t('Failed to write config file'));
    }

    return ['success' => true, 'message' => t('Settings updated successfully')];
}

// Функция для получения всех пользователей
function getAllUsers($user)
{
    $users = $user->getAllUsers();

    if ($users) {
        // Преобразуем ассоциативный массив в индексный и убираем хеши паролей
        return ['success' => true, 'users' => array_values(array_map('publicUserData', $users))];
    } else {
        return ['success' => false, 'message' => t('No users found')];
    }
}


// Функция для получения отсутствующих переводов
function getMissingTranslations()
{
    try {
        $translator = Translator::getInstance();
        $missingTranslations = $translator->manageMissing('get');

        return [
            'success' => true,
            'translations' => $missingTranslations
        ];
    } catch (Exception $e) {
        logMessage('Ошибка получения отсутствующих переводов: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => t('Failed to get missing translations')
        ];
    }
}

// Функция для получения списка доступных языков
function getValidLanguages()
{
    try {
        $languages = Translator::getValidLanguages();
        return [
            'success' => true,
            'languages' => $languages
        ];
    } catch (Exception $e) {
        logMessage('Ошибка в getValidLanguages: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => t('Error loading languages list')
        ];
    }
}

// Функция для получения перевода по ключу и языку
function getTranslation()
{
    $key = $_POST['key'] ?? '';
    $lang = $_POST['lang'] ?? '';

    // Логируем ключ и язык
    //logMessage("Getting translation for key: $key, language: $lang", 'INFO');

    if (empty($key)) {
        throw new Exception(t('Translation key is required'));
    }

    try {
        // Используем функцию t() с отключенным отслеживанием отсутствующих переводов
        $translation = t($key, $lang, false);
        return [
            'success' => true,
            'translation' => $translation,
            'key' => $key,
            'language' => $lang ?: Translator::getCurrentLanguage()
        ];
    } catch (Exception $e) {
        logMessage('Ошибка получения перевода: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => t('Failed to get translation')
        ];
    }
}

// Функция для удаления отсутствующего перевода
function deleteMissingTranslation()
{
    $key = $_POST['key'] ?? '';

    if (empty($key)) {
        logMessage('Получен пустой ключ для удаления перевода', 'ERROR');
        return ['success' => false, 'message' => t('Translation key is required')];
    }

    try {
        $translator = Translator::getInstance();
        //logMessage("Попытка удалить отсутствующий перевод для ключа: $key", 'INFO');

        if (!$translator->manageMissing('delete', $key)) {
            logMessage("Не удалось удалить отсутствующий перевод для ключа: $key", 'ERROR');
            return [
                'success' => false,
                'message' => t('Failed to delete missing translation')
            ];
        }

        return [
            'success' => true,
            'message' => t('Missing translation deleted successfully')
        ];
    } catch (Exception $e) {
        logMessage('Ошибка удаления отсутствующего перевода: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => t('Failed to delete missing translation')
        ];
    }
}

// 
function saveTranslation()
{
    $key = $_POST['original'] ?? '';
    $translation = $_POST['translation'] ?? '';
    $lang = $_POST['lang'] ?? '';

    if (empty($key) || empty($translation) || empty($lang)) {
        logMessage('Отсутствуют обязательные параметры для сохранения перевода', 'ERROR');
        return [
            'success' => false,
            'message' => t('Missing required parameters')
        ];
    }

    try {
        $translator = Translator::getInstance();

        // Сначала проверим существует ли уже такой перевод
        $translations = $translator->manageTranslations('get', $lang);

        if (isset($translations[$key])) {
            // Если перевод существует - обновляем
            $result = $translator->manageTranslations('update', $lang, $key, $translation);
            //logMessage("Обновление существующего перевода для ключа: $key, язык: $lang", 'INFO');
        } else {
            // Если перевода нет - добавляем
            $result = $translator->manageTranslations('add', $lang, $key, $translation);
            //logMessage("Добавление нового перевода для ключа: $key, язык: $lang", 'INFO');
        }

        if ($result) {
            // Убираем ключ из списка «Не найденные переводы»
            // Убираем из «Не найденные переводы», только если ключ уже есть во ВСЕХ языках
            $allDone = true;
            foreach (Translator::getValidLanguages() as $l) {
                $tr = $translator->manageTranslations('get', $l);
                if (!is_array($tr) || !isset($tr[$key])) { $allDone = false; break; }
            }
            if ($allDone) {
                $translator->manageMissing('delete', $key);
            }
            return [
                'success' => true,
                'message' => t('Translation saved successfully')
            ];
        } else {
            logMessage("Не удалось сохранить перевод для ключа: $key, язык: $lang", 'ERROR');
            return [
                'success' => false,
                'message' => t('Failed to save translation')
            ];
        }
    } catch (Exception $e) {
        logMessage('Ошибка сохранения перевода: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => t('Failed to save translation')
        ];
    }
}

// Удаляет перевод ключа для языка и возвращает ключ в список отсутствующих переводов
function deleteTranslation()
{
    $key  = $_POST['key'] ?? '';
    $lang = $_POST['lang'] ?? '';

    if ($key === '' || $lang === '') {
        return ['success' => false, 'message' => t('Missing required parameters')];
    }

    try {
        $translator   = Translator::getInstance();
        $translations = $translator->manageTranslations('get', $lang);

        if (!is_array($translations) || !array_key_exists($key, $translations)) {
            return ['success' => false, 'message' => t('Failed to delete translation')];
        }

        if (!$translator->manageTranslations('delete', $lang, $key)) {
            logMessage("Не удалось удалить перевод для ключа: $key, язык: $lang", 'ERROR');
            return ['success' => false, 'message' => t('Failed to delete translation')];
        }

        // Перевода больше нет хотя бы на одном языке, значит ключ снова нужно перевести
        $translator->manageMissing('add', $key);

        return ['success' => true];
    } catch (Exception $e) {
        logMessage('Ошибка удаления перевода: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => t('Failed to delete translation')];
    }
}

// Добавим новую функцию для поиска оригинального ключа
function findOriginalKey()
{
    $translation = $_POST['translation'] ?? '';

    if (empty($translation)) {
        return [
            'success' => false,
            'message' => t('Translation text is required')
        ];
    }

    try {
        //$translator = Translator::getInstance();
        $key = Translator::findOriginalKey($translation);

        if ($key !== null) {
            return [
                'success' => true,
                'key' => $key,
                'translation' => $translation
            ];
        } else {
            return [
                'success' => false,
                'message' => t('Original key not found for this translation')
            ];
        }
    } catch (Exception $e) {
        logMessage('Ошибка поиска оригинального ключа: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => t('Error searching for original key')
        ];
    }
}

function clearCache()
{
    global $cmsConfig;  // Используем глобальную переменную

    $cacheDir = BOARD_ROOT . DIRECTORY_SEPARATOR . $cmsConfig['tplCacheDir'];
    $success = true;

    // Проверяем, существует ли директория кэша
    if (!is_dir($cacheDir)) {
        return [
            'success' => false,
            'message' => t('Cache directory does not exist.')
        ];
    }

    // Открываем директорию
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    // Удаляем все файлы и поддиректории
    foreach ($items as $item) {
        // Файлы защиты папки кэша (.htaccess, index.html) не удаляем
        if (in_array($item->getFilename(), ['.htaccess', 'index.html'], true) &&
            $item->getPath() === realpath($cacheDir)) {
            continue;
        }
        if ($item->isDir()) {
            // Удаляем директорию
            if (!rmdir($item->getRealPath())) {
                $success = false;
                break;
            }
        } else {
            // Удаляем файл
            if (!unlink($item->getRealPath())) {
                $success = false;
                break;
            }
        }
    }

    // Возвращаем статус операции
    return [
        'success' => $success,
        'message' => $success ? t('Cache cleared successfully') : t('Failed to clear cache')
    ];
}

// Основной код
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        // hash_equals - сравнение за постоянное время (без утечки по времени ответа)
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrfToken)) {
            logMessage('Недействительный CSRF-токен', 'WARNING'); // значение заголовка в журнал не пишем
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => t('Access denied')]);
            exit;
        }

        $action = $_POST['action'] ?? '';
        handleAction($action, $user, $storage);
    } catch (Throwable $e) {
        logMessage('Ошибка ajax.php: ' . $e->getMessage(), 'ERROR');
        echo json_encode([
            'success' => false,
            'message' => t('Unknown error occurred')
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => t('Method not supported')]);
exit;
