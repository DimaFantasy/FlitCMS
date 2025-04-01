<?php

/**
 * Класс Translator:
 * 1. configure - Настройка конфигурации переводчика.
 * 2. getInstance - Получение экземпляра класса.
 * 3. validateLanguage - Проверка валидности языкового кода.
 * 4. loadTranslations - Загрузка переводов для указанного языка.
 * 5. loadMissingTranslations - Загрузка списка отсутствующих переводов.
 * 6. translate - Получение перевода по ключу.
 * 7. manageTranslations - Управление переводами (получение/обновление/удаление/добавление).
 * 8. manageMissing - Управление списком отсутствующих переводов.
 * 9. removeMissing - Удаление ключа из списка отсутствующих переводов.
 * 10. getMissingTranslations - Получение списка отсутствующих переводов.
 * 11. getValidLanguages - Получение списка поддерживаемых языков.
 * 12. getCurrentLanguage - Получение текущего активного языка.
 * 13. findOriginalKey - Поиск ключа по значению перевода.
 * 14. t - Функция для упрощенного доступа к переводу.
 */

class Translator
{
    private static $instance = null;

    private static $config = [
        'langDir'     => '',
        'missingFile' => '',
        'languages'   => [],
        'defaultLang' => '',
    ];

    private $translations = [];
    private $missing      = [];

    private function __construct($lang)
    {
        $lang = $this->validateLanguage($lang);
        $this->loadTranslations($lang);
        $this->loadMissingTranslations();
    }

    public static function configure($langDir, $missingFile, $languages, $defaultLang)
    {
        self::$config = [
            'langDir'     => rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($langDir)), DIRECTORY_SEPARATOR),
            'missingFile' => str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($missingFile)),
            'languages'   => is_string($languages) ? array_map('trim', explode(',', $languages)) : $languages,
            'defaultLang' => $defaultLang,
        ];
    }

    /**
     * Получение экземпляра класса
     * @param string|null $lang Код языка
     * @return Translator
     */
    public static function getInstance($lang = null)
    {
        if (self::$instance === null) {
            self::$instance = new self($lang ?? self::$config['defaultLang']);
        }
        return self::$instance;
    }

    /**
     * Проверка валидности языкового кода
     * @param string $lang Код языка
     * @return string Валидный код языка
     */
    private function validateLanguage($lang)
    {
        return in_array($lang, self::$config['languages']) ? $lang : self::$config['defaultLang'];
    }

    /**
     * Загрузка переводов для указанного языка
     * @param string $lang Код языка
     */
    private function loadTranslations($lang)
    {
        $file = self::$config['langDir'] . DIRECTORY_SEPARATOR . $lang . '.json';
        if (file_exists($file)) {
            $content            = file_get_contents($file);
            $this->translations = json_decode($content, true) ?: [];
        }
    }

    /**
     * Загрузка списка отсутствующих переводов
     */
    private function loadMissingTranslations()
    {
        $file = self::$config['langDir'] . DIRECTORY_SEPARATOR . self::$config['missingFile'];
        if (! file_exists($file)) {
            file_put_contents($file, '');
        }
        $this->missing = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    }

    /**
     * Получение перевода по ключу
     * @param string $key Ключ перевода
     * @param string|null $lang Код языка
     * @param bool $trackMissing Добавлять ли отсутствующий перевод в список (по умолчанию true)
     * @return string Переведенный текст или ключ в квадратных скобках
     */
    public static function translate($key, $lang = null, $trackMissing = true)
    {
        $instance = self::getInstance($lang);

        // Принудительно загружаем переводы для запрашиваемого языка
        if ($lang !== null) {
            $instance->loadTranslations($instance->validateLanguage($lang));
        }

        // Приводим ключ к нижнему регистру и обрезаем пробелы для поиска
        $searchKey = strtolower(trim($key));

        // Поиск перевода в словаре
        foreach ($instance->translations as $original => $translation) {
            // Приводим оригинальный ключ к нижнему регистру и обрезаем пробелы для сравнения
            $originalKey = strtolower(trim($original));

            // Если ключи совпадают, возвращаем перевод
            if ($originalKey === $searchKey) {
                return $translation;
            }
        }

        // Если перевода нет, проверяем, нужно ли добавить отсутствующий перевод в список
        if ($trackMissing && ! in_array(strtolower($key), array_map('strtolower', $instance->missing))) {
            $instance->missing[] = $key;

            // Записываем оригинальный ключ в файл пропущенных переводов
            $file = self::$config['langDir'] . DIRECTORY_SEPARATOR . self::$config['missingFile'];
            file_put_contents($file, $key . PHP_EOL, FILE_APPEND);
        }

        // Если перевод не найден, возвращаем оригинальный ключ в квадратных скобках
        return "[$key]";
    }

    /**
     * Управление переводами (получение/обновление/удаление/добавление)
     * @param string $action Действие (get/update/delete/add)
     * @param string $lang Код языка
     * @param string|null $key Ключ перевода
     * @param string|null $newValue Новое значение перевода (для update/add)
     * @return bool|array Результат операции или массив переводов
     */
    public function manageTranslations($action, $lang, $key = null, $newValue = null)
    {
        if (! in_array($lang, self::$config['languages'])) {
            return false;
        }

        $file = self::$config['langDir'] . DIRECTORY_SEPARATOR . $lang . '.json';

        // Проверяем, существует ли файл с переводами
        $translations = file_exists($file)
            ? (json_decode(file_get_contents($file), true) ?: [])
            : [];

        // комады обновления/удаления/добавления
        switch ($action) {
            case 'get': // Возвращаем все переводы
                return $translations;
            case 'update': // Обновляем перевод по ключу
                $translations[$key] = $newValue;
                break;
            case 'delete': // Удаляем перевод по ключу
                unset($translations[$key]);
                break;
            case 'add': // Добавляем новый перевод
                if (! isset($translations[$key])) {
                    $translations[$key] = $newValue;
                } else {
                    return false; // Ключ уже существует
                }
                break;
        }

        return file_put_contents(
            $file,
            json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ) !== false;
    }

    /**
     * Управление списком отсутствующих переводов
     * @param string $action Действие (get/update/delete/add)
     * @param string|null $key Ключ перевода
     * @param string|null $newKey Новое значение ключа (для update)
     * @return bool|array Результат операции или массив отсутствующих переводов
     */
    public function manageMissing($action, $key = null, $newKey = null)
    {
        $file = self::$config['langDir'] . DIRECTORY_SEPARATOR . self::$config['missingFile'];

        // Всегда перезагружаем актуальный список из файла
        $this->loadMissingTranslations();

        switch ($action) {
            case 'get':
                return array_values(array_unique($this->missing));

            case 'add':
                if ($key === null || in_array($key, $this->missing)) {
                    return false;
                }
                $this->missing[] = trim($key);
                break;

            case 'update':
                if ($key === null || $newKey === null) {
                    return false;
                }
                $key    = trim($key);
                $newKey = trim($newKey);
                $index  = array_search($key, $this->missing);
                if ($index !== false) {
                    $this->missing[$index] = $newKey;
                } else {
                    return false;
                }
                break;

            case 'delete':
                if ($key === null) {
                    return false;
                }
                $key           = trim($key);
                $this->missing = array_values(array_filter(
                    $this->missing,
                    function ($entry) use ($key) {
                        return strtolower(trim($entry)) !== strtolower($key);
                    }
                ));
                break;

            default:
                return false;
        }

        // Удаляем дубликаты и пустые строки перед сохранением
        $this->missing = array_values(array_unique(array_filter($this->missing, 'strlen')));

        // Сохраняем изменения в файл
        $success = file_put_contents($file, implode(PHP_EOL, $this->missing) .
            (empty($this->missing) ? '' : PHP_EOL));

        return $success !== false;
    }

    /**
     * Удаление ключа из списка отсутствующих переводов
     * @param string $key Ключ для удаления
     */
    private function removeMissing($key)
    {
        $this->missing = array_filter(
            $this->missing,
            fn($entry) => strtolower(trim($entry)) !== strtolower(trim($key))
        );

        $file = self::$config['langDir'] . DIRECTORY_SEPARATOR . self::$config['missingFile'];
        file_put_contents($file, implode(PHP_EOL, $this->missing) .
            (empty($this->missing) ? '' : PHP_EOL));
    }

    /**
     * Получение списка отсутствующих переводов
     * @return array Массив отсутствующих переводов
     */
    public static function getMissingTranslations()
    {
        return array_values(array_unique(self::getInstance()->missing));
    }

    /**
     * Получение списка поддерживаемых языков
     * @return array Массив кодов языков
     */
    public static function getValidLanguages()
    {
        return self::$config['languages'];
    }

    /**
     * Получение текущего активного языка
     * @return string Код текущего языка
     */
    public static function getCurrentLanguage()
    {
        return self::getInstance()->validateLanguage(null);
    }

    /**
     * Поиск ключа по значению перевода (регистронезависимый поиск)
     * @param string $searchKey Значение перевода для поиска
     * @return string|null Найденный ключ или null, если значение не найдено
     */
    public static function findOriginalKey($searchKey)
    {
        //logMessage('Начало поиска значения: ' . $searchKey, 'INFO');

        if (empty($searchKey)) {
            //logMessage('Пустое значение для поиска', 'INFO');
            return null;
        }

        $searchKey = strtolower(trim($searchKey));

        foreach (self::$config['languages'] as $lang) {
            $file = self::$config['langDir'] . DIRECTORY_SEPARATOR . $lang . '.json';

            if (! file_exists($file)) {
                continue;
            }

            $translations = json_decode(file_get_contents($file), true) ?: [];

            foreach ($translations as $key => $value) {
                if (strtolower(trim($value)) === $searchKey) {
                    return $key; // Возвращаем ключ, соответствующий найденному значению
                }
            }
        }

        return null;
    }
}

// Функция перевода
// $key - Слово для перевода (строка)
// $lang - Язык для перевода (по умолчанию null, используется текущий язык при инициализации Translator)
// $trackMissing - добавлять отсутствующий перевод в список отсутствуших переводов (по умолчанию true добавляет)
// Возвращает переведенный текст или ключ в квадратных скобках, если перевод не найден
function t($key, $lang = null, $trackMissing = true)
{
    return Translator::translate($key, $lang, $trackMissing);
}
