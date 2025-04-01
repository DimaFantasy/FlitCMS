<?php

/**
 * Класс Logger реализует систему логирования с ротацией лог-файлов.
 */
class Logger
{
    // Единственный экземпляр класса (Singleton)
    private static $instance = null;

    // Директория для хранения логов
    private $logDir;

    // Имя лог-файла по умолчанию
    private $defaultLogFile;

    // Максимальный размер лог-файла в байтах (по умолчанию 1MB)
    private $maxSize;

    /**
     * Приватный конструктор (чтобы запретить создание объекта через new).
     *
     * @param string $logDir Директория для логов
     * @param string $defaultLogFile Имя лог-файла по умолчанию
     * @param int $maxSize Максимальный размер лог-файла (1MB по умолчанию)
     */
    private function __construct(
        $logDir = BOARD_ROOT . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR,
        $defaultLogFile = 'app.log',
        $maxSize = 1048576
    ) {
        // Инициализация свойств
        $this->logDir = $logDir;
        $this->defaultLogFile = $defaultLogFile;
        $this->maxSize = $maxSize;

        // Создаем папку для логов, если её нет
        if (!is_dir($this->logDir)) {
            if (!mkdir($this->logDir, 0777, true)) {
                throw new Exception("Не удалось создать директорию для логов: {$this->logDir}");
            }
        }

        // Путь к файлу лога
        $logFilePath = $this->logDir . $this->defaultLogFile;

        // Создаем файл лога, если его нет
        if (!file_exists($logFilePath)) {
            if (!touch($logFilePath)) {
                throw new Exception("Не удалось создать файл лога: {$logFilePath}");
            }
        }

        // Проверяем размер файла и обрабатываем, если превышен лимит
        if (file_exists($logFilePath) && filesize($logFilePath) > $this->maxSize) {
            // Логика для обработки превышения размера (например, создание нового файла)
            // В данном случае можно создать новый лог-файл с текущей датой.
            $newLogFile = $this->logDir . 'app_' . date('Y-m-d_H-i-s') . '.log';
            if (!rename($logFilePath, $newLogFile)) {
                throw new Exception("Не удалось архивировать старый лог: {$logFilePath}");
            }
            // Создаем новый файл для логов
            if (!touch($logFilePath)) {
                throw new Exception("Не удалось создать новый файл лога: {$logFilePath}");
            }
        }
    }

    /**
     * Получает (или создает) экземпляр Logger (Singleton).
     *
     * @param string $logDir Директория для логов
     * @param string $defaultLogFile Имя лог-файла по умолчанию
     * @param int $maxSize Максимальный размер лог-файла
     * @return Logger Экземпляр Logger
     */
    public static function getInstance(
        $logDir = BOARD_ROOT . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR,
        $defaultLogFile = 'app.log',
        $maxSize = 1048576
    ) {
        // Если экземпляр еще не создан, создаем его
        if (self::$instance === null) {
            self::$instance = new self($logDir, $defaultLogFile, $maxSize);
        }
        return self::$instance;
    }

    /**
     * Записывает сообщение в лог-файл.
     *
     * @param string $message Текст сообщения
     * @param string $level Уровень логирования (INFO, ERROR, DEBUG и т. д.)
     * @param string|null $logfile Имя лог-файла (если null, используется defaultLogFile)
     */
    public function logMessage($message, $level = 'INFO', $logfile = null)
    {
        // Если имя файла не указано, используем файл по умолчанию
        $logfile = $logfile ?? $this->defaultLogFile;
        $logpath = $this->logDir . $logfile;

        // Формируем строку для записи в лог
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;

        // Записываем сообщение в лог-файл
        file_put_contents($logpath, $logMessage, FILE_APPEND);

        // Проверяем, не нужно ли выполнить ротацию логов
        $this->rotateLogs($logfile);
    }

    /**
     * Ротация лог-файлов (если файл превышает maxSize, он архивируется).
     *
     * @param string $logfile Имя лог-файла
     */
    private function rotateLogs($logfile)
    {
        // Формируем полный путь к лог-файлу
        $logpath = $this->logDir . $logfile;
        // Если файл существует и его размер превышает maxSize
        if (file_exists($logpath)) {
            if (filesize($logpath) >= $this->maxSize) {
                // Создаем имя для архивированного файла
                $backupPath = $this->logDir . date('Y-m-d_His') . '_' . $logfile;
                // Переименовываем текущий файл в архивный
                rename($logpath, $backupPath);
            }
        }
    }

    /**
     * Получает содержимое лог-файла.
     *
     * @param bool $reverse Если true, то возвращает строки в обратном порядке (сначала последние)
     * @return string Содержимое лог-файла
     * @throws Exception Если файл не существует
     */
    public function getLogContent($reverse = true)
    {
        // Используем файл по умолчанию
        $logpath = $this->logDir . $this->defaultLogFile;
        // Проверяем, существует ли файл
        if (!file_exists($logpath)) {
            throw new Exception("Лог-файл не существует: {$logpath}");
        }
        // Читаем содержимое файла
        $logContent = file($logpath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        // Если требуется сортировать, делаем это
        if ($reverse) {
            $logContent = array_reverse($logContent);
        }
        // Возвращаем содержимое как строку, объединяя элементы массива
        return implode(PHP_EOL, $logContent);
    }


    /**
     * Очищает содержимое лог-файла.
     *
     * @param string|null $logfile Имя лог-файла (если null, используется defaultLogFile)
     */
    public function logClear($logfile = null)
    {
        // Если имя файла не указано, используем файл по умолчанию
        $logfile = $logfile ?? $this->defaultLogFile;
        $logpath = $this->logDir . $logfile;
        // Проверяем, существует ли файл
        if (file_exists($logpath)) {
            // Очищаем содержимое файла
            file_put_contents($logpath, '');
        } else {
            throw new Exception("Лог-файл не существует: {$logpath}");
        }
    }
}


/**
 * Глобальная функция для удобного логирования.
 *
 * @param string $message Текст сообщения
 * @param string $level Уровень логирования (INFO, ERROR, DEBUG)
 * @param string|null $logfile Имя лог-файла
 */
function logMessage($message, $level = 'INFO', $logfile = null)
{
    Logger::getInstance()->logMessage($message, $level, $logfile);
}

/**
 * Глобальная функция для очистки содержимого лог-файла.
 *
 * @param string|null $logfile Имя лог-файла
 */
function logClear($logfile = null)
{
    Logger::getInstance()->logClear($logfile);
}

/**
 * Глобальная функция для удобного получения содержимого лог-файла.
 *
 * @param string|null $logfile Имя лог-файла
 * @return string Содержимое лог-файла
 */
function getLogContent($logfile = null)
{
    return Logger::getInstance()->getLogContent($logfile);
}

/*
// Пример использования
require 'class.logging.php';

// Логирование через глобальную функцию
logMessage('Test message'); // Запись в app.log
logMessage('Error message', 'ERROR'); // Запись в app.log с уровнем ERROR
logMessage('Debug message', 'DEBUG', 'debug.log'); // Запись в debug.log с уровнем DEBUG
*/
