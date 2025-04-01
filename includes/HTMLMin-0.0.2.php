<?php

class HTMLMin
{
    public static function minify($html)
    {
        // Убираем HTML-комментарии (кроме условных комментариев для IE и сохраненных блоков BLOCK_[0-9a-f])
        $html = preg_replace('/<!--(?!\[if.*?\]|\s*BLOCK_\w+).*?-->/s', '', $html);

        // Убираем пробелы и переносы строк до и после HTML-комментариев
        $html = preg_replace_callback(
            // Регулярное выражение для поиска HTML-комментариев
            '/([^>]*)<!--(.*?)-->([^<]*)/iu', 
            function ($matches) {
                // $matches[1] - все до комментария (включая пробелы)
                // $matches[2] - сам комментарий (всё, что между <!-- и -->)
                // $matches[3] - всё после комментария (включая пробелы)
        
                // Заменяем несколько пробелов в начале содержимого до комментария
                $before = preg_replace('/\s+/', ' ', $matches[1]);
        
                // Заменяем несколько пробелов после комментария
                $after = preg_replace('/\s+/', ' ', $matches[3]);
        
                // Возвращаем строку, в которой пробелы до и после комментария заменены на одиночные
                return $before . '<!--' . $matches[2] . '-->' . $after;
            },
            $html
        );

        // Убираем лишние пробелы и переносы строк везде
        // $html = preg_replace('/\s{2,}/', ' ', $html);
        // Тоже работает
        // Убираем пробелы между закрывающими и открывающими тегами
        //$html = preg_replace('/>[\s]*?</', '><', $html);

        //  в списке для удаления пробелов и переносов в настройках тега
        $whiteSpaceTags =
            'area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr|'
            . 'li|ul|span|div|button|a|nav|body|footer|head|html|form';

        // Список тегов, для удаления пробелов после тегов 
        $tagsWithSpaces =
            'area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr|'
            . 'li|ul|span|div|button|a|nav|body|footer|head|html|form|select|table|thead|'
            . 'tr|td|tbody|template|svg';

        // Основные теги HTML5 и другие
        $blockTags =
            'a|abbr|address|area|article|aside|audio|b|base|bdi|bdo|blockquote|body|br|button'
            . '|canvas|caption|cite|code|col|colgroup|data|datalist|dd|del|details|dfn|dialog|div|dl|dt'
            . '|em|embed|fieldset|figcaption|figure|footer|form|h[1-6]|head|header|hgroup|hr|html|i|iframe'
            . '|img|input|ins|kbd|label|legend|li|link|main|map|mark|menu|meta|meter|nav|noscript|object'
            . '|ol|optgroup|option|output|p|param|picture|pre|progress|q|rp|rt|ruby|s|samp|script|section'
            . '|select|small|source|span|strong|style|sub|summary|sup|table|tbody|td|template|textarea'
            . '|tfoot|th|thead|time|title|tr|track|u|ul|var|video|wbr|svg'
            // Новые теги (HTML Living Standard)
            . '|search|portal|popover|selectmenu'
            // MathML
            . '|math|mi|mo|mn|ms|mtext'
            // Устаревшие теги (по желанию)
            . '|acronym|applet|basefont|big|blink|center|dir|font|isindex|listing|marquee|multicol|nextid|plaintext|spacer|strike|tt|xmp';

        // Обработка HTML
        $html = preg_replace_callback(
            '/(\s*)(<\s*(\/?)(' . $blockTags . ')\s*(?:\s+([^>]*))?\s*(\/?)\s*>)([^<]+)/iu',
            function ($matches) use ($whiteSpaceTags, $tagsWithSpaces) {
                // $matches[0] - Полное совпадение с регулярным выражением (весь найденный фрагмент)
                // $matches[1] - Пробелы перед тегом (если есть)
                // $matches[2] - Полный тег (например, "<div>", "<p class='text'>")
                // $matches[3] - Символ закрытия тега "/" для закрывающих тегов (например, в "</div>")
                // $matches[4] - Имя тега (например, "div", "p", "span")
                // $matches[5] - Атрибуты тега (если есть, например, "class='text' id='main'")
                // $matches[6] - Символ самозакрывающегося тега "/" (например, в "<br />")
                // $matches[7] - Содержимое после тега до следующего "<" (например, текст внутри тега)
                $tag = $matches[2]; // Полный тег
                $closingTag = $matches[3]; // Символ закрытия
                $tagName = $matches[4]; // Имя тега
                $content = $matches[7] ?? ''; // Содержимое после тега (если есть)

                // Если тег в списке для удаления пробелов и переносов в настройках тега
                if (preg_match('/\b(' . $whiteSpaceTags . ')\b/', $tagName)) {
                    // Удаляем пробелы и переносы строк внутри тега
                    $tag = preg_replace('/\s+/', ' ', $tag);
                }

                // Если тег закрыт или в списке для удаления пробелов после тегов
                if (!empty($closingTag) || preg_match('/\b(' . $tagsWithSpaces . ')\b/', $tagName)) {
                    // Меняем все пробелы и переносы строк на один пробел
                    $content = preg_replace('/\s+/', ' ', $content);
                    return $tag . $content; // Возвращаем с изменённым содержимым
                }

                // Оставляем без изменений
                return $tag . $content;
            },
            $html
        );
        // Убираем пробелы между тегами, если они не в списке для удаления пробелов и переносов в настройках тега
        return trim($html); // обрезаем концы и возвращяем
     
        /*
        Тоже работает но страница должна быть цельная dom не целые элементы удаляет максимум оставит в конце </body></html>
        $dom = new DOMDocument('1.0', 'UTF-8');
        // Загружаем HTML без изменения структуры
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);
        // Списки элементов
        $preserve_space_tags = ['b', 'strong', 'i', 'em', 'u', 'a', 'span', 'button']; // Между ними пробел сохраняем на 1
        $inline_tags = ['b', 'strong', 'i', 'em', 'u', 'a', 'span', 'small', 'sup', 'sub']; // Inline-элементы
        $nodes = $xpath->query('//text()[not(ancestor::pre) and not(ancestor::code) and not(ancestor::textarea)]');
        foreach ($nodes as $node) {
            $prev = $node->previousSibling;
            $next = $node->nextSibling;

            $prev_tag = ($prev && $prev->nodeType === XML_ELEMENT_NODE) ? strtolower($prev->nodeName) : null;
            $next_tag = ($next && $next->nodeType === XML_ELEMENT_NODE) ? strtolower($next->nodeName) : null;

            $keep_space = false;

            // Если предыдущий и следующий элементы в списке "опасных" → оставляем пробел
            if ($prev_tag && $next_tag) {
                if (in_array($prev_tag, $preserve_space_tags) && in_array($next_tag, $preserve_space_tags)) {
                    $keep_space = true;
                }
            }

            // Если пробел нужно сохранить, добавляем его, иначе убираем лишние пробелы
            if ($keep_space) {
                $node->nodeValue = ' ' . preg_replace('/\s+/u', ' ', trim($node->nodeValue)) . ' ';
            } else {
                $node->nodeValue = preg_replace('/\s+/u', ' ', trim($node->nodeValue));
            }
        }
        // Возвращаем исправленный HTML
        $html = $dom->saveHTML();
        // Декодируем HTML-сущности, если нужно
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        */
    }
}
