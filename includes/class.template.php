<?php
/**
 * Класс Template:
 * 1. getInstance($config = []) - Получение единственного экземпляра класса (Singleton).
 * 2. getTemplateConfig() - Возвращает текущую конфигурацию шаблонов.
 * 3. compile($tplName) - Компилирует указанный шаблон и возвращает путь к скомпилированному файлу.
 * 4. clearCache() - Очищает директорию кэша шаблонов.
 * 5. joinPaths(...$parts) - Объединяет части пути в корректный путь с учетом разделителей.
 * 6. parse($tpl) - Парсит шаблонный файл, заменяя конструкции {тег} на соответствующий PHP-код.
 * 7. template($page) - Статическая обертка для компиляции шаблона.
 */

//require_once 'includes/jsmin-1.1.2.php';
//require_once 'includes/CSSMin-0.0.1.php';
require_once 'includes/HTMLMin-0.0.2.php';

// Обновляем подключения минификатора JS и CSS
require_once 'includes/matthiasmullie_minify/src/Minify.php';
require_once 'includes/matthiasmullie_minify/src/JS.php';
require_once 'includes/matthiasmullie_minify/src/CSS.php';
require_once 'includes/matthiasmullie_minify/src/Exception.php';
require_once 'includes/matthiasmullie_minify/src/Exceptions/BasicException.php';
require_once 'includes/matthiasmullie_minify/src/Exceptions/FileImportException.php';
require_once 'includes/matthiasmullie_minify/src/Exceptions/IOException.php';
require_once 'includes/matthiasmullie_minify/src/PathConverter/ConverterInterface.php';
require_once 'includes/matthiasmullie_minify/src/PathConverter/Converter.php';
// Подключаем классы из библиотеки Minify для минификации JS и CSS

use MatthiasMullie\Minify\CSS; // Подключаем класс CSS для минификации CSS
use MatthiasMullie\Minify\JS; // Подключаем класс JS для минификации JS



class Template
{
    private static $instance = null; // Статическое свойство для хранения объекта
    private $tplCompress; // Сжатие шаблонов (по умолчанию false)
    private $tplForceCompile; // Принудительная компиляция (по умолчанию false)
    private $tplRootDir; // Корневая директория шаблонов (по умолчанию 'templates')
    private $tplVerDir; // Версия директории шаблонов (по умолчанию 'default')
    private $tplCacheDir; // Директория кэша шаблонов (по умолчанию 'cache')

    private function __construct($config)
    {
        $this->tplCompress     = $config['tplCompress'] ?? false;
        $this->tplForceCompile = $config['tplForceCompile'] ?? false;
        $this->tplRootDir      = $config['tplRootDir'] ?? 'templates';
        $this->tplVerDir       = $config['tplVerDir'] ?? 'default';
        $this->tplCacheDir     = $config['tplCacheDir'] ?? 'cache';
    }

    // Singleton: получаем экземпляр Template (инициализируется один раз)
    public static function getInstance($config = [])
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    // Метод для получения текущей конфигурации шаблонов
    public function getTemplateConfig()
    {
        return [
            'tplCompress'     => $this->tplCompress,
            'tplForceCompile' => $this->tplForceCompile,
            'tplRootDir'      => $this->tplRootDir,
            'tplVerDir'       => $this->tplVerDir,
            'tplCacheDir'     => $this->tplCacheDir,
        ];
    }

    public function compile($tplName)
    {
        // Формируем путь к файлу шаблона
        $templateFile = $this->joinPaths(
            BOARD_ROOT,
            $this->tplRootDir,
            $this->tplVerDir,
            $tplName
        );
        // Формируем путь к файлу кэша
        $cacheFile = $this->joinPaths(
            BOARD_ROOT,
            $this->tplCacheDir,
            $this->tplVerDir,
            $tplName . '.php'
        );

        // Если принудительная компиляция отключена, проверяем необходимость компиляции
        if (! $this->tplForceCompile && file_exists($cacheFile)) {
            // Проверяем, если шаблон не был изменен после последней компиляции
            if (@filemtime($templateFile) <= @filemtime($cacheFile)) {
                return $cacheFile; // Возвращаем путь к кэшированному файлу, если компиляция не требуется
            }
        }
        // Проверка существования шаблона
        if (! file_exists($templateFile)) {
            logMessage("Template file not found: {$templateFile}", "ERROR");
            return false;
        }
        // Чтение содержимого шаблона
        $content = file_get_contents($templateFile);
        if ($content === false) {
            logMessage("Failed to read template: {$templateFile}", "ERROR");
            return false;
        }
        // Парсинг шаблона
        $compiledContent = $this->parse($content);
        // Создаем директорию для кэша
        $cacheDir = dirname($cacheFile);
        if (! is_dir($cacheDir)) {
            if (! mkdir($cacheDir, 0777, true)) {
                logMessage("Failed to create cache directory: {$cacheDir}", "ERROR");
                return false;
            }
        }
        // Сохраняем скомпилированный шаблон
        if (file_put_contents($cacheFile, $compiledContent) === false) {
            logMessage("Failed to write cache file: {$cacheFile}", "ERROR");
            return false;
        }
        // Устанавливаем права (если возможно)
        @chmod($cacheFile, 0777);
        // Возвращаем путь к скомпилированному файлу
        return $cacheFile;
    }

    public function clearCache()
    {
        // Формируем путь
        $cacheDir = $this->joinPaths(BOARD_ROOT, $this->tplCacheDir);

        // Проверяем, существует ли директория кэша
        if (! is_dir($cacheDir)) {
            return;
        }

        // Открываем директорию
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        // Удаляем все файлы и поддиректории
        foreach ($items as $item) {
            try {
                if ($item->isDir()) {
                    // Удаляем директорию
                    rmdir($item->getRealPath());
                } else {
                    // Удаляем файл
                    unlink($item->getRealPath());
                }
            } catch (Exception $e) {
                // Логирование ошибки или вывод сообщения
                error_log("Ошибка при удалении элемента: " . $item->getRealPath() . " - " . $e->getMessage(), 'ERROR');
            }
        }
    }

    private function joinPaths(...$parts)
    {
        $normalized = [];
        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            $normalized[] = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part);
        }
        return preg_replace(
            '#' . preg_quote(DIRECTORY_SEPARATOR) . '+#',
            DIRECTORY_SEPARATOR,
            implode(DIRECTORY_SEPARATOR, $normalized)
        );
    }

    /**
     * Парсит шаблонный файл и заменяет конструкцию {тег} на соответствующий PHP-код.
     *
     * @param string $tpl Исходный шаблон с тегами вида {if}, {loop}, {php}, {include}
     * @return string Преобразованный шаблонный код с PHP
     */
    private function parse($tpl)
    {

        /* Добавляем защиту от прямого доступа к файлу */
        /*$tpl = "<?php if(!defined('BOARD'))die('Access Denied'); ?>" . $tpl;*/

        /* Вставка подшаблонов: {{template footer}} -> <?php include template('footer'); ?> */
        $tpl = preg_replace("/\{\{template\s+(.+)\}\}/", "\n<?php include template(str_replace('/', DIRECTORY_SEPARATOR, '\\1')); ?>\n", $tpl);

        /* Вставка файлов напрямую: {{include file.php}} -> <?php include 'file.php'; ?> */
        $tpl = preg_replace("/\{\{include\s+(.+)\}\}/", "\n<?php include \\1; ?>\n", $tpl);

        /* Вставка кода PHP: {{php echo 'Hello';}} -> <?php echo 'Hello'; ?> */
        $tpl = preg_replace("/\{\{php\s+(.+?)\}\}/s", "<?php \\1 ?>", $tpl);

        /* Блоки условий */
        $tpl = preg_replace("/\{\{if\s+(.+?)\}\}/", "<?php if(\\1) { ?>", $tpl);
        $tpl = preg_replace("/\{\{else\}\}/", "<?php } else { ?>", $tpl);
        $tpl = preg_replace("/\{\{elseif\s+(.+?)\}\}/", "<?php } elseif (\\1) { ?>", $tpl);
        $tpl = preg_replace("/\{\{\/if\}\}/", "<?php } ?>", $tpl);

        /* Циклы for */
        $tpl = preg_replace("/\{\{for\s+(.+?)\}\}/", "<?php for (\\1) { ?>", $tpl);
        $tpl = preg_replace("/\{\{endfor\}\}/", "<?php } ?>", $tpl);

        /* Циклы foreach */
        $tpl = preg_replace("/\{\{loop\s+(\S+)\s+(\S+)\}\}/", "<?php if(is_array(\\1)) foreach(\\1 AS \\2) { ?>", $tpl);
        $tpl = preg_replace("/\{\{loop\s+(\S+)\s+(\S+)\s+(\S+)\}\}/", "\n<?php if(is_array(\\1)) foreach(\\1 AS \\2 => \\3) { ?>", $tpl);
        $tpl = preg_replace("/\{\{\/loop\}\}/", "\n<?php } ?>\n", $tpl);

        /* Вставка функций PHP: {{time()}} -> <?php echo time(); ?> */
        $tpl = preg_replace("/\{\{([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\(([^{}]*)\))\}\}/", "<?php echo \\1;?>", $tpl);

        /* Вставка переменных: {{$variable}} -> <?php echo $variable; ?> */
        $tpl = preg_replace(
            "/\{\{\s*(\\$\w+(?:\[(?:'?[a-zA-Z0-9_]+'?|[0-9]+|\\$[a-zA-Z0-9_]+|[A-Z_][A-Z0-9_]+|)\]|\->\w+(?:\([^\)]*\))?)*)\s*\}\}/",
            "<?php echo \\1; ?>",
            $tpl
        );

        /* Константы PHP: {{SITE_NAME}} -> <?php echo SITE_NAME; ?> */
        $tpl = preg_replace("/\{\{([A-Z_\x7f-\xff][A-Z0-9_\x7f-\xff]*)\}\}/s", "<?php echo \\1;?>", $tpl);

        if ($this->tplCompress) { // Если включено сжатие шаблона

            /**
             * Функция генерации уникального идентификатора для сохраненных блоков
             */
            $javaUniqueId = function () {
                return 'BLOCK_' . bin2hex(random_bytes(16));
            };

            // Хранилище для временного сохранения блоков (скрипты, стили, PHP)
            $savedBlocks = [];

            /**
             * Универсальная функция для обработки блоков (скрипты, стили, PHP)
             *
             * @param string $pattern  Регулярное выражение для поиска блока
             * @param string $type     Тип блока (script, style, php)
             */
            $processBlock = function ($pattern, $type) use (&$tpl, &$savedBlocks, $javaUniqueId) {
                $tpl = preg_replace_callback($pattern, function ($matches) use (&$savedBlocks, $javaUniqueId, $type) {
                    // Генерируем уникальный идентификатор для блока
                    $placeholder = $javaUniqueId();
                    // Сохраняем блок во временный массив
                    $savedBlocks[$placeholder] = [
                        'type'    => $type,
                        'content' => $matches[0],
                    ];
                    // Заменяем найденный блок на комментарий-заглушку
                    return "<!--{$placeholder}-->";
                }, $tpl);
            };

            /**
             * Поочередно обрабатываем разные типы блоков
             */
            $processBlock('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', 'script'); // Сохраняем JavaScript
            $processBlock('/<\s*style\b[^>]*>.*?<\s*\/\s*style\s*>/is', 'style');    // Сохраняем CSS
            $processBlock('/<\s*\?php.*?\?\s*>/is', 'php');                          // Сохраняем PHP-вставки

            // Минифицируем HTML-код (убираем лишние пробелы и переносы строк)
            $tpl = HTMLMin::minify($tpl);

            /**
             * Функции минификации для каждого типа блоков
             * Используем анонимные функции для удобства и гибкости
             */

            $minifiers = [
                'script' => fn($content) => (new JS($content))->minify(),
                'style'  => fn($content)  => (new CSS($content))->minify(),
                'php'    => fn($content)    => $content,
            ];

            /**
             * Восстанавливаем сохраненные блоки в исходный HTML-код
             */
            foreach ($savedBlocks as $placeholder => $block) {
                // Получаем оригинальный контент блока
                $original = $block['content'];

                // Возвращаем оригинальный контент блока в зависимости от его типа
                if (isset($minifiers[$block['type']])) {
                    $original = $minifiers[$block['type']]($original);
                }

                // Вставляем обратно блок вместо заглушки, убирая лишние пробелы с краев
                $tpl = str_replace("<!--{$placeholder}-->", trim($original), $tpl);
            }
        }

        return $tpl; // Возвращаем финальный обработанный шаблон

    }
    // Статическая обертка для вызова template($page)
    public static function template($page)
    {
        $instance = self::getInstance();
        return $instance->compile($page) ?: '';
    }
}

function template($page)
{
    return Template::getInstance()->compile($page);
}
/*
// Пример использования:
$template = new Template([
    'tplRootDir' => 'templates',
    'tplVerDir' => 'default',
    'tplCacheDir' => 'cache',
]);
$template->compile('index.htm'); // Компилируем шаблон index.htm
template('index.htm'); // Статическая обертка для компиляции шаблона
// $template->clearCache(); // Очищаем кэш шаблонов
*/
