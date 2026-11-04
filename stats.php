<?php

/**
 * Мониторинг системных ресурсов (CPU и память) для Windows и Linux.
 * Доступен только администратору. Отдаёт JSON: {"used_memory": МБ, "cpu": %}.
 */

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

/**
 * Windows: один вызов PowerShell (медленный старт) возвращает загрузку CPU и память.
 * Скрипт передаётся через -EncodedCommand, чтобы не мучиться с кавычками.
 */
function getWindowsStats()
{
    $script = '$c=(Get-CimInstance Win32_PerfFormattedData_PerfOS_Processor -Filter "Name=\'_Total\'").PercentProcessorTime;'
        . 'if($c -eq $null){$c=(Get-CimInstance Win32_Processor|Measure-Object LoadPercentage -Average).Average};'
        . '$o=Get-CimInstance Win32_OperatingSystem;'
        . 'Write-Output ("{0};{1};{2}" -f $c,$o.TotalVisibleMemorySize,$o.FreePhysicalMemory)';
    // -EncodedCommand = base64 от UTF-16LE (скрипт только из ASCII, поэтому достаточно добавить нулевые байты)
    $encoded = base64_encode(implode("\0", str_split($script)) . "\0");
    $out = (string)shell_exec('C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe -NoProfile -NonInteractive -EncodedCommand ' . $encoded);

    $parts = explode(';', trim(str_replace(',', '.', $out)));
    if (count($parts) < 3) {
        return ['used_memory' => 0, 'cpu' => 0];
    }
    $cpu = max(0, min(100, (float)$parts[0]));
    $usedMemMb = ((float)$parts[1] - (float)$parts[2]) / 1024; // KB -> MB

    return ['used_memory' => round($usedMemMb, 2), 'cpu' => round($cpu, 2)];
}

/**
 * Linux: снимок счётчиков CPU из /proc/stat -> [простой, всего]
 */
function readCpuTimes()
{
    $line = @file('/proc/stat', FILE_IGNORE_NEW_LINES);
    if (!$line || strpos($line[0], 'cpu ') !== 0) {
        return null;
    }
    $v = array_map('floatval', preg_split('/\s+/', trim(substr($line[0], 4))));
    // user nice system idle iowait irq softirq steal
    $idle  = ($v[3] ?? 0) + ($v[4] ?? 0);
    $total = array_sum(array_slice($v, 0, 8));
    return [$idle, $total];
}

function getLinuxStats()
{
    // Память: used из `free -b`
    $memInfo = (string)shell_exec('LC_ALL=C free -b');
    if (!preg_match('/Mem:\s+(\d+)\s+(\d+)/', $memInfo, $m)) {
        return ['used_memory' => 0, 'cpu' => 0];
    }
    $usedMem = $m[2] / 1024 / 1024; // MB

    // CPU: два снимка /proc/stat с паузой 300 мс - это именно ТЕКУЩАЯ загрузка.
    // (`top -bn1` даёт среднее с момента запуска системы, а на локали с запятой ещё и не разбирается)
    $cpuLoad = null;
    $a = readCpuTimes();
    if ($a) {
        usleep(300000);
        $b = readCpuTimes();
        if ($b && $b[1] > $a[1]) {
            $cpuLoad = 100 * (1 - ($b[0] - $a[0]) / ($b[1] - $a[1]));
        }
    }
    if ($cpuLoad === null) { // запасной вариант: второй замер top (первый - среднее с запуска)
        $top = (string)shell_exec('LC_ALL=C top -bn2 -d 0.5 | grep "Cpu(s)" | tail -1');
        $cpuLoad = preg_match('/([\d.]+)\s*id/', $top, $c) ? 100 - (float)$c[1] : 0;
    }
    $cpuLoad = max(0, min(100, $cpuLoad));

    return ['used_memory' => round($usedMem, 2), 'cpu' => round($cpuLoad, 2)];
}

// Определяем ОС и получаем статистику
$stats = ['used_memory' => 0, 'cpu' => 0];
if (function_exists('shell_exec') && stripos((string)ini_get('disable_functions'), 'shell_exec') === false) {
    try {
        $stats = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? getWindowsStats() : getLinuxStats();
    } catch (Throwable $e) {
        // оставляем нули: статистика не критична
    }
}

echo json_encode($stats);
