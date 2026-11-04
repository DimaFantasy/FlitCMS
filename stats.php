<?php

/**
 * Мониторинг системных ресурсов
 * 
 * Этот файл содержит функции для получения информации о загрузке
 * системы (CPU и памяти) для Windows и Linux систем
 * 
 * @version 1.0
 */

/**
 * Получает статистику использования ресурсов для Windows систем
 * 
 * @return array Массив с данными:
 *               - used_memory (float) Использованная память в МБ
 *               - cpu (float) Загрузка процессора в процентах
 */
function getWindowsStats()
{
    // Получаем загрузку процессора с помощью PowerShell
    $cpuLoad = shell_exec("C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe -Command \"Get-CimInstance -ClassName Win32_Processor | Select-Object LoadPercentage\"");

    // Парсим вывод и извлекаем процент загрузки процессора
    preg_match('/\d+/', $cpuLoad, $cpuLoadMatches);
    $cpuLoad = isset($cpuLoadMatches[0]) ? (int)$cpuLoadMatches[0] : 0;

    // Получаем использование памяти с помощью PowerShell
    $memInfo = shell_exec("C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe -Command \"Get-CimInstance -ClassName Win32_OperatingSystem | Select-Object FreePhysicalMemory, TotalVisibleMemorySize\"");

    // Парсим вывод и извлекаем значения
    preg_match_all('/\d+/', $memInfo, $matches);

    // Получаем общее количество и свободное количество памяти в килобайтах
    $totalMemKb = isset($matches[0][1]) ? $matches[0][1] : 0; // Общее количество памяти в KB
    $freeMemKb = isset($matches[0][0]) ? $matches[0][0] : 0;  // Свободная память в KB

    // Вычисляем занятую память в мегабайтах
    $usedMemMb = ($totalMemKb - $freeMemKb) / 1024; // Занятая память в MB

    return [
        'used_memory' => round($usedMemMb, 2),
        'cpu' => round($cpuLoad, 2)
    ];
}

/**
 * Получает статистику использования ресурсов для Linux систем
 * 
 * @return array Массив с данными:
 *               - used_memory (float) Использованная память в МБ
 *               - cpu (float) Загрузка процессора в процентах
 */
function getLinuxStats()
{
    // Получаем данные о памяти через команду free
    $memInfo = shell_exec('free -b');  // Получаем данные в байтах

    // Разбираем строку с использованием регулярного выражения
    preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $memInfo, $matches);

    // Получаем общую, используемую и свободную память
    if (count($matches) < 3) {
        return ['used_memory' => 0, 'cpu' => 0];
    }
    $totalMem = $matches[1] / 1024 / 1024;  // Преобразуем в MB
    $usedMem = $matches[2] / 1024 / 1024;

    // Получаем загрузку процессора через команду top (для однократного обновления)
    $cpuLoadInfo = shell_exec('top -bn1 | grep "Cpu(s)"');

    // Извлекаем значение загрузки процессора
    preg_match('/(\d+\.\d+)\s*id/', $cpuLoadInfo, $cpuMatches);
    $cpuIdle = isset($cpuMatches[1]) ? (float)$cpuMatches[1] : 100.0;  // Процент простоя процессора
    $cpuLoad = 100 - $cpuIdle;  // Загрузка процессора = 100 - idle

    return [
        'used_memory' => round($usedMem, 2),
        'cpu' => round($cpuLoad, 2)
    ];
}

// Доступ только администратору (проверка по хранилищу, а не по роли в сессии)
define('BOARD', true);
define('BOARD_ROOT', __DIR__);
chdir(__DIR__);

require_once 'includes/class.logger.php';
require_once 'includes/class.user.php';
require_once 'includes/class.database.php';
$cmsConfig = require 'config/config.inc.php';

$allowed = false;
try {
    if ($cmsConfig['storage'] === 'mysql') {
        $storage   = new MysqlTableStorage($cmsConfig);
        $tableName = $cmsConfig['mysql']['UsersTableName'];
    } else {
        $storage   = new JsonTableStorage($cmsConfig['json']);
        $tableName = $cmsConfig['json']['UsersTableName'];
    }
    $user    = new User($storage, $tableName, $cmsConfig['sessionKey'], $cmsConfig['lifeTime'], $cmsConfig['userCookeName']);
    $allowed = $user->isAdmin();
} catch (Throwable $e) {
    logMessage('stats.php: ' . $e->getMessage(), 'ERROR');
}
// Сессия больше не нужна: освобождаем блокировку, чтобы частые запросы не выстраивались в очередь
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Заголовки ставим после запуска сессии (session_start() выставляет свои Cache-Control)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (!$allowed) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ только для администратора.']);
    exit;
}

// Файл публичный, а каждый вызов запускает системные команды (top / PowerShell).
// Чтобы им нельзя было нагрузить сервер, результат кэшируется на 5 секунд.
$cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flitcms_stats_' . md5(__DIR__) . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 5) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false && json_decode($cached) !== null) {
        echo $cached;
        exit;
    }
}

// Определяем текущую операционную систему и получаем соответствующую статистику
$stats = ['used_memory' => 0, 'cpu' => 0];
if (function_exists('shell_exec') && stripos((string)ini_get('disable_functions'), 'shell_exec') === false) {
    try {
        $stats = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? getWindowsStats() : getLinuxStats();
    } catch (Throwable $e) {
        // оставляем нули: статистика не критична
    }
}

$json = json_encode($stats);
@file_put_contents($cacheFile, $json, LOCK_EX);

// Выводим результат в формате JSON
echo $json;
