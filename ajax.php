<?php
// Определяем константу для защиты от прямого доступа к файлам
define('BOARD', true);
// Устанавливаем путь к корневой директории
define('BOARD_ROOT', __DIR__);

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
    } catch (PDOException $e) {
        logMessage("Ошибка подключения к базе данных: " . $e->getMessage(), 'ERROR');
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

    try {
        switch ($action) {
            // Регистрация нового пользователя
            case 'register_user':
                $response = registerUser($user);
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

            // Поиск оригинального ключа по переводу
            case 'find_original_key':
                $response = findOriginalKey();
                break;

            case 'clear_cache':
                $response = clearCache();
                break;
        }
    } catch (Exception $e) {
        logMessage('Ошибка в действии ' . $action . ': ' . $e->getMessage(), 'ERROR');
        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'action' => $action,
            'errorCode' => $e->getCode()
        ];
    }

    echo json_encode($response);
    exit;
}

// Функция для регистрации пользователя
function registerUser($user)
{
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $name = $_POST['name'] ?? '';
    $role = $_POST['role'] ?? 'USER';
    $autoLogin = isset($_POST['autoLogin']) ? filter_var($_POST['autoLogin'], FILTER_VALIDATE_BOOLEAN) : true;

    if (empty($email) || empty($password) || empty($name)) {
        throw new Exception(t('Please fill in all fields'));
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
    $email = $_POST['email'] ?? '';
    if (empty($email)) {
        throw new Exception(t('Email is not specified'));
    }

    $user = $user->getUserByEmail($email);
    if ($user) {
        return ['success' => true, 'user' => $user];
    } else {
        return ['success' => false, 'message' => t('User not found')];
    }
}

// Функция для удаления пользователя
function deleteUser($user)
{
    $email = $_POST['email'] ?? '';

    if (empty($email)) {
        throw new Exception(t('Email is not specified'));
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
    $data = json_decode($_POST['data'], true); // Условие для поиска записи
    $dataUpdate = json_decode($_POST['dataUpdate'], true); // Данные для обновления

    // Убедиться, что json_decode() не возвращает null
    if (!is_array($data) || !is_array($dataUpdate)) {
        // Ошибка в декодировании JSON
        return ['success' => false, 'message' => t('Invalid input data')];
    }

    // Проверка наличия обязательных полей
    if (!isset($data['email']) || !isset($dataUpdate['name']) || !isset($dataUpdate['role'])) {
        // Ошибка в отсутствии обязательных полей
        return ['success' => false, 'message' => t('Missing required fields')];
    }

    try {
        // Используем метод updateRecord
        $updatedCount = $user->updateRecord($data, $dataUpdate);
        if ($updatedCount > 0) {
            return ['success' => true, 'message' => t('User updated successfully')];
        } else {
            return ['success' => false, 'message' => t('Failed to update user')];
        }
    } catch (Exception $e) {
        error_log(t('Ошибка в update_user: ') . $e->getMessage());
        // Возвращаем ошибку, если исключение поймано
        return ['success' => false, 'message' => t('Error updating user: ') . $e->getMessage()];
    }
}

// Функция для сброса пароля
function resetPassword($user)
{
    $email = $_POST['email'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';

    if (empty($email) || empty($newPassword)) {
        throw new Exception(t('Email and new password are required'));
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
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        throw new Exception(t('Please fill in all fields'));
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
    $email = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';

    if (empty($email) || empty($message)) {
        throw new Exception(t('Please fill in all fields'));
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
    if (file_put_contents($configFile, implode('', $newLines)) === false) {
        throw new Exception(t('Failed to write config file'));
    }

    return ['success' => true, 'message' => t('Settings updated successfully')];
}

// Функция для получения всех пользователей
function getAllUsers($user)
{
    $users = $user->getAllUsers();
    if ($users) {
        // Преобразуем ассоциативный массив в индексный
        return ['success' => true, 'users' => array_values($users)];
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

        if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
            logMessage('Недействительный CSRF-токен: ' . $csrfToken, 'WARNING');
            echo json_encode(['success' => false, 'message' => t('Access denied')]);
            exit;
        }

        $action = $_POST['action'] ?? '';
        handleAction($action, $user, $storage);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => t('Method not supported')]);
exit;
