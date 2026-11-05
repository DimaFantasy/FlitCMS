<?php
// Определяем константу для защиты от прямого доступа к файлам
define('BOARD', true);
// Устанавливаем путь к корневой директории
define('BOARD_ROOT', __DIR__);

// Базовые защитные заголовки
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

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
// Функция перевода, которая возвращает текст
function t($key)
{
    return Translator::translate($key); // Возвращает переведенное значение
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
            $cmsConfig['mysql']['UsersTableName'], // Название таблицы пользователей  в случае с Json путь к имени файла
            $cmsConfig['sessionKey'], // Ключ сессии
            // Здесь нужно добавить время жизни сессии
            $cmsConfig['lifeTime'], // Время жизни сессии, например, 1 для 1 часа
            $cmsConfig['userCookeName']
        );
        $dataStorage = new MysqlTableStorage($cmsConfig);
    } catch (Throwable $e) {
        logMessage("Ошибка подключения к базе данных: " . $e->getMessage(), 'ERROR');
        http_response_code(503);
        exit('Service temporarily unavailable');
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
    http_response_code(503);
    exit('Service temporarily unavailable');
}

// Общий заголовок для всех страниц
$DESCRIPTION = $cmsConfig['siteName'] . (!empty($cmsConfig['version']) ? ' v' . $cmsConfig['version'] : '');
// Определяем активную страницу из параметра URL
// Принимаем только строку (page[]=... иначе приводит к ошибке типов); само имя проверяет Template::compile()
$ACTIVE_PAGE = (isset($_GET['page']) && is_string($_GET['page'])) ? $_GET['page'] : 'index.htm'; // По умолчанию загружаем главную страницу

// http://localhost/?page=admin&isAdmin
// Проверяем, является ли пользователь администратором и передан ли параметр isAdmin если да, то подключаем админский шаблон
$isAdminRequest = isset($_GET['isAdmin']) && $user->isAdmin(); // проверка по хранилищу, а не по роли в сессии
$currentTplDir = $isAdminRequest
    ? $cmsConfig['tplAdminDir'] // Папка для админки
    : $cmsConfig['tplDir']; // Стандартная папка

// Админка для не-администратора: честный 403 Forbidden, без редиректов и подмены страницы
if (isset($_GET['isAdmin']) && !$isAdminRequest) {
    http_response_code(403);
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<meta name="robots" content="noindex"><title>403</title></head><body style="font-family:sans-serif;text-align:center;margin-top:15vh">'
        . '<h1>403</h1><p>' . htmlspecialchars(t('Access denied'), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><a href="?page=index.htm">' . htmlspecialchars(t('Home Page'), ENT_QUOTES, 'UTF-8') . '</a></p></body></html>';
    exit;
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