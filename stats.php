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
    $totalMem = $matches[1] / 1024 / 1024;  // Преобразуем в MB
    $usedMem = $matches[2] / 1024 / 1024;

    // Получаем загрузку процессора через команду top (для однократного обновления)
    $cpuLoadInfo = shell_exec('top -bn1 | grep "Cpu(s)"');

    // Извлекаем значение загрузки процессора
    preg_match('/(\d+\.\d+)\s*id/', $cpuLoadInfo, $cpuMatches);
    $cpuIdle = $cpuMatches[1];  // Процент простоя процессора
    $cpuLoad = 100 - $cpuIdle;  // Загрузка процессора = 100 - idle

    return [
        'used_memory' => round($usedMem, 2),
        'cpu' => round($cpuLoad, 2)
    ];
}

// Определяем текущую операционную систему и получаем соответствующую статистику
$stats = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? getWindowsStats() : getLinuxStats();

// Выводим результат в формате JSON
echo json_encode($stats);
