<?php
// Определяем константу для защиты от прямого доступа к файлам
define('BOARD', true);
// Устанавливаем путь к корневой директории
define('BOARD_ROOT', __DIR__);

// Подключаем основные функции
require_once 'includes/class.logger.php';
require_once 'includes/class.user.php';
require_once 'includes/class.template.php';
require_once 'includes/class.database.php';
require_once 'includes/class.translator.php';
// Загружаем конфиги
$cmsConfig = require 'config/config.inc.php';

// Определяем язык из куки, используя имя куки из конфигурации (по умолчанию 'LANG')
$ACTIVE_LANGUAGE = $_COOKIE[$cmsConfig['langCookieName']] ?? $_POST['lang'] ?? $cmsConfig['defaultLanguage'];
Translator::configure(
    $cmsConfig['langDir'], // Директория с языковыми файлами
    $cmsConfig['missingTranslationsFileName'], // Файл для отсутствующих переводов
    $cmsConfig['validLanguages'], // Список допустимых языков
    $ACTIVE_LANGUAGE // Основной язык
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
            $cmsConfig['mysql']['UsersTableName'], // Название таблицы пользователей  в случае с Json путь к имени файла
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
        $storage, // Объект хранилища (MysqlTableStorage, JsonTableStorage)
        $cmsConfig['json']['UsersTableName'], // Название таблицы пользователей  в случае с Json путь к имени файла
        $cmsConfig['sessionKey'], // Ключ сессии
        // Здесь нужно добавить время жизни сессии
        $cmsConfig['lifeTime'], // Время жизни сессии, например, 1 для 1 часа
        $cmsConfig['userCookeName']
    );
    $dataStorage = new JsonTableStorage($cmsConfig['json']);
} else {
    logMessage("Неизвестный тип хранилища: $storageType", 'ERROR');
}

// Общий заголовок для всех страниц
$DESCRIPTION = $cmsConfig['siteName'] . (!empty($cmsConfig['version']) ? ' v' . $cmsConfig['version'] : '');
// Определяем активную страницу из параметра URL
$ACTIVE_PAGE = $_GET['page'] ?? 'index.htm'; // По умолчанию загружаем главную страницу

// http://localhost/?page=admin&isAdmin
// Проверяем, является ли пользователь администратором и передан ли параметр isAdmin если да, то подключаем админский шаблон
$currentTplDir = ($user->isCurrentRole('Admin') && isset($_GET['isAdmin']))
    ? $cmsConfig['tplAdminDir'] // Папка для админки
    : $cmsConfig['tplDir']; // Стандартная папка

// Если пытаются зайти в админ панель а пользователь НЕ администратор, перенаправляем на index.htm
if (isset($_GET['isAdmin']) && !$user->isCurrentRole('Admin')) {
    $ACTIVE_PAGE = 'index.htm';
    $currentTplDir = $cmsConfig['tplDir']; // Стандартная папка
}    
    
// Подключаем шаблонизатор
Template::getInstance([
    'tplCompress' => $cmsConfig['tplCompress'] ?? false,
    'tplForceCompile' => $cmsConfig['tplForceCompile'] ?? false,
    'tplRootDir' => $cmsConfig['tplRootDir'] ?? 'templates',
    'tplVerDir' => $currentTplDir,
    'tplCacheDir' => $cmsConfig['tplCacheDir'] ?? 'cache',
]);

// Подключаем шаблон
$templatePath = template($ACTIVE_PAGE);
if (!empty($templatePath)) {
    include $templatePath;
} else {
    /* Отправляем HTTP-заголовок 404 Not Found (страница не найдена) */
    header("HTTP/1.1 404 Not Found");
    /* Выводим сообщение и завершаем выполнение скрипта */
    exit('<h1>404 Not Found</h1><p>The requested page was not found on this server.</p>');
}

/*
// Создаем таблицу пользователей
$columns = ['name', 'email'];
$dataStorage->createTable('user_test', $columns);
// Добавление записей
$dataStorage->insertRecord('user_test', ['name' => 'Dima', 'email' => 'dima@example.com']);
$dataStorage->insertRecord('user_test', ['name' => 'dima', 'email' => 'dima2@example.com']);
$dataStorage->insertRecord('user_test', ['name' => 'John', 'email' => 'john@example.com']);
// Регистрозависимый поиск
$strictResults = $dataStorage->getRecordsByColumn('user_test', 'name', 'Dima', true);
print_r($strictResults); // Найдет только 'Dima'
// Регистронезависимый поиск
$caseInsensitiveResults = $dataStorage->getRecordsByColumn('user_test', 'name', 'Dima', false);
print_r($caseInsensitiveResults); // Найдет 'Dima' и 'dima'
*/
?>