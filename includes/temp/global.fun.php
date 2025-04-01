<?php

if (!defined('BOARD')) {
    die('Access Denied');
}

/*
1. cut_str - Обрезает строку до заданной длины, поддерживает многобайтовые символы.
2. cut_str_next - Обрезает строку до заданной длины и добавляет "..." в конце, если строка длиннее.
3. str_len - Возвращает длину строки с учетом многобайтовых символов.
4. addslashes_deep - Рекурсивно экранирует кавычки в массиве или строке.
5. stripslashes_deep - Рекурсивно удаляет экранирование кавычек в массиве или строке.
6. htmlspecialchars_deep - Рекурсивно преобразует специальные символы в HTML-сущности.
7. random - Генерирует случайную строку заданной длины.
8. is_email - Проверяет, является ли строка корректным email-адресом.
9. checkupfile - Проверяет, был ли файл загружен через HTTP POST.
10. fileext - Возвращает расширение файла.
11. get_ip - Получает IP-адрес клиента.
12. encrypt - Шифрует строку с использованием пароля.
13. decrypt - Расшифровывает строку с использованием пароля.
14. chkcode - Генерирует изображение капчи.
15. page - Генерирует данные для пагинации.
16. get_pager - Создает пагинацию с использованием URL и параметров.
17. url_rewrite - Генерирует URL с учетом правил переписывания.
18. enddate - Возвращает количество дней до указанной даты.
19. template - Компилирует шаблон, если он изменился.
20. clear_caches - Очищает кэш файлов в заданной директории.
21. get_parent_cat - Получает список родительских категорий.
22. get_cat_children_id - Возвращает ID дочерних категорий.
23. get_parent_area - Получает список родительских регионов.
24. get_area_info - Получает информацию о регионе по его ID.
25. get_config - Получает конфигурацию сайта.
26. get_pay - Получает настройки платежных систем.
27. get_infout - Получает настройки вывода информации.
28. get_link_list - Получает список ссылок (текстовых и с изображениями).
29. get_info - Получает список информации с фильтрацией по категориям и регионам.
30. get_flash - Получает данные для флеш-баннеров.
31. get_nav - Получает данные для навигации.
32. get_info_custom - Получает пользовательские данные для информации.
33. get_custom_info - Получает данные о пользовательских полях.
34. cat_search_custom - Генерирует HTML для пользовательских полей поиска.
35. get_domain - Возвращает домен сайта.
36. url - Возвращает базовый URL сайта.
37. ads_list - Получает список рекламных объявлений.
38. member_info - Получает информацию о пользователе по его ID.
39. template_compile - Компилирует шаблон в PHP-файл.
40. template_parse - Парсит шаблон и преобразует его в PHP-код.
41. addquote - Добавляет кавычки к строке.
42. read_cache - Читает данные из кэша.
43. write_cache - Записывает данные в кэш.
44. onlyarea - Проверяет доступность добавления объявлений для региона.
45. check_code - Проверяет капчу.
46. check_words - Проверяет текст на наличие запрещенных слов.
47. login - Авторизует пользователя.
48. check_user - Проверяет существование пользователя.
49. logout - Завершает сессию пользователя.
50. set_session - Устанавливает данные сессии пользователя.
51. register - Регистрирует нового пользователя.
52. gen_new_char - Генерирует случайный пароль.
53. register_hide - Регистрирует пользователя без логина.
54. less_to_css - Компилирует LESS-файлы в CSS.
55. compress_js - Сжимает JavaScript-файлы.
56. array_to_ol - Преобразует массив в HTML-список (нумерованный).
57. select_post_form - Выбирает шаблон для категории.
58. flag_reg - Проверяет строку на соответствие регулярному выражению.
59. flag_null - Проверяет, пуста ли строка.
60. flag_strlen - Проверяет длину строки.
61. check_nzz - Проверяет строку на соответствие определенным правилам.
62. no_injection - Защищает строку от SQL-инъекций.
63. base64mail - Кодирует строку для использования в email-заголовках.
64. get_money_list - Возвращает список валют.
65. money_options - Генерирует HTML-опции для выбора валюты.
66. is_temp_dir - Проверяет или создает временную директорию.
67. is_usr_dir - Проверяет или создает директорию пользователя.
68. is_promo_dir - Проверяет или создает директорию для промо.
69. get_background - Получает список фонов.
70. get_normalize_index - Нормализует данные для индекса.
71. get_normalize_tpl_default - Нормализует данные для списка объявлений.
72. redirect_html - Генерирует HTML для редиректа.
73. get_image_thumb - Возвращает имя миниатюры изображения.
74. removeDirectory - Удаляет директорию и ее содержимое.
75. phone_format - Форматирует номер телефона.
76. get_product_list - Получает список товаров.
77. get_area_list - Получает список регионов.
78. get_area_child_list - Получает список дочерних регионов.
79. get_area_children - Возвращает ID дочерних регионов через запятую.
80. area_options - Генерирует HTML-опции для выбора региона.
81. get_cat_list - Получает список категорий.
82. get_cat_child_list - Получает список подкатегорий.
83. get_cat_info - Получает информацию о категории.
84. get_cat_children_html - Генерирует HTML для дочерних категорий.
85. get_cat_children - Возвращает ID дочерних категорий через запятую.
86. get_count_category - Возвращает количество записей в категориях.
87. get_count_category_all - Возвращает общее количество записей.
88. get_count_category_new - Возвращает количество новых записей.
89. create_db - Создает базу данных SQLite для пользователя.
*/

function cut_str($str, $length, $start = 0)
{
    global $charset;
    if (function_exists("mb_substr")) {
        if (mb_strlen($str, $charset) <= $length) return $str;
        $slice = mb_substr($str, $start, $length, $charset);
    } else { /////// Обработка различных ошибок связанных с неверным формированием UTF-8
        $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        preg_match_all($re[$charset], $str, $match);
        if (count($match[0]) <= $length) return $str;
        $slice = join("", array_slice($match[0], $start, $length));
    }
    return $slice;
}

function cut_str_next($str, $length, $start = 0)
{
    global $charset;
    if (function_exists("mb_substr")) {
        if (mb_strlen($str, $charset) <= $length) return $str;
        $slice = mb_substr($str, $start, $length, $charset);
    } else { /////// Обработка различных ошибок связанных с неверным формированием UTF-8
        $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        preg_match_all($re[$charset], $str, $match);
        if (count($match[0]) <= $length) return $str;
        $slice = join("", array_slice($match[0], $start, $length));
    }
    if ( mb_strlen($str) > mb_strlen($slice) ) {$slice = $slice . '...';}

    return $slice;
}

function str_len($str)
{
    $length = strlen(preg_replace('/[\x00-\x7F]/', '', $str));
    if ($length) {
        return strlen($str) - $length + intval($length / 3) * 2;
    } else {
        return strlen($str);
    }
}

function addslashes_deep($value)
{
    return is_array($value) ? array_map('addslashes_deep', $value) : addslashes($value);
}

function stripslashes_deep($value)
{
    return is_array($value) ? array_map('stripslashes_deep', $value) : (isset($value) ? stripslashes($value) : null);
}

function htmlspecialchars_deep($value)
{
    $value = is_array($value) ? array_map('htmlspecialchars_deep', $value) : htmlspecialchars($value, ENT_QUOTES);
    return $value;
}

function random($length)
{
    $hash = '';
    $chars = '0123456789ABCDEFGHIJ0123456789KLMNOPQRSTJ0123456789UVWXYZ0123456789abcdefghijJ0123456789klmnopqrstJ0123456789uvwxyz0123456789';
    $max = strlen($chars);
    mt_srand((double)microtime() * 1000000);
    for ($i = 0; $i < $length; $i++) {
        $hash .= $chars[mt_rand(0, $max)];
    }
    return $hash;
}

function is_email($email)
{
    return strlen($email) > 8 && preg_match("/^[-_+.[:alnum:]]+@((([[:alnum:]]|[[:alnum:]][[:alnum:]-]*[[:alnum:]])\.)+([a-z]{2,4})|(([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])\.){3}([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5]))$/i", $email);
}

function checkupfile($file)
{
    return function_exists('is_uploaded_file') && (is_uploaded_file($file) || is_uploaded_file(str_replace('\\\\', '\\', $file)));
}

function fileext($filename)
{
    return trim(substr(strrchr($filename, '.'), 1));
}

function get_ip()
{
    static $ip = NULL;
    if ($ip !== NULL) {
        return $ip;
    }
    if (isset($_SERVER)) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($arr as $ip) {
                $ip = trim($ip);
                if ($ip != 'unknown') {
                    $ip = $ip;
                    break;
                }
            }
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            } else {
                $ip = '0.0.0.0';
            }
        }
    } else {
        if (getenv('HTTP_X_FORWARDED_FOR')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('HTTP_CLIENT_IP')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } else {
            $ip = getenv('REMOTE_ADDR');
        }
    }
    preg_match("/[\d\.]{7,15}/", $ip, $onlineip);
    $ip = !empty($onlineip[0]) ? $onlineip[0] : '0.0.0.0';

    return $ip;
}

function encrypt($string, $password)
{
    $pwd = '';
    $asciis = '';
    $password = base64_encode($password);
    $count_pwd = strlen("a" . $password);
    for ($i = 1; $i < $count_pwd; $i++) {
        $pwd += ord($password[$i]);
    }
    $string = base64_encode($string);
    $count = strlen("a" . $string);
    for ($i = 0; $i < $count; $i++) {
        $asciis .= (ord($string[$i]) + $pwd) . "|";
    }
    $asciis = base64_encode($asciis);
    return $asciis;
}

function decrypt($string, $password)
{
    $pwd = '';
    $infos = '';
    $password = base64_encode($password);
    $count_pwd = strlen("a" . $password);
    for ($i = 1; $i < $count_pwd; $i++) {
        $pwd += ord($password[$i]);
    }
    $string = base64_decode($string);
    $contents = explode("|", $string);
    $count = count($contents);
    for ($i = 0; $i < $count; $i++) {
        $infos .= chr($contents[$i] - $pwd);
    }
    $asciis = base64_decode($infos);
    return $asciis;
}

function chkcode($width = 90, $height = 28, $let_amount = 4, $font_size = 9, $fontName ='fonts/Helvetica.ttf' , $fon_let_amount = 10)
{
    // $width = 80;                  //Ширина изображения
    // $height = 25;                  //Высота изображения
    // $font_size = 9;               //Размер шрифта
    // $let_amount = 4;               //Количество символов, которые нужно набрать
    // $fon_let_amount = 10;          //Количество символов, которые находятся на фоне
   // $fontName ='fonts/Helvetica.ttf'  // Путь к фонтам
//    $path_fonts = BOARD_ROOT . 'fonts/'; //Путь к шрифтам

    $letters = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'j', 'k', 'm', 'n', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '2', '3', '4', '5', '6', '7', '9');
    $colors = array('10', '30', '50', '70', '90', '110', '130', '150', '170', '190', '210');
    $src = imagecreatetruecolor($width, $height);
    $fon = imagecolorallocate($src, 255, 255, 255);
    imagefill($src, 0, 0, $fon);

    $fonts = array( BOARD_ROOT . $fontName);

//    $dir = opendir($path_fonts);
//    while ($fontName = readdir($dir)) {
//        if ($fontName != "." && $fontName != "..") {
//            $fonts[] = $fontName;
//        }
//    }
//    closedir($dir);

    for ($i = 0; $i < $fon_let_amount; $i++) {
        $color = imagecolorallocatealpha($src, rand(0, 255), rand(0, 255), rand(0, 255), 100);
        $font =  $fonts[rand(0, sizeof($fonts) - 1)];
        $letter = $letters[rand(0, sizeof($letters) - 1)];
        $size = rand($font_size - 2, $font_size + 2);
        imagettftext($src, $size, rand(0, 45), rand($width * 0.1, $width - $width * 0.1), rand($height * 0.2, $height), $color, $font, $letter);
    }
    $widthx = floor($width / $let_amount) ;
    for ($i = 0; $i < $let_amount; $i++) {
        $color = imagecolorallocatealpha($src, $colors[rand(0, sizeof($colors) - 1)], $colors[rand(0, sizeof($colors) - 1)], $colors[rand(0, sizeof($colors) - 1)], rand(20, 40));
        $font =  $fonts[rand(0, sizeof($fonts) - 1)];
        $letter = $letters[rand(0, sizeof($letters) - 1)];
        $size = rand($font_size * 2.1 - 2, $font_size * 2.1 + 2);
        $x = ($i * $widthx ) + rand(4,10);  /// 7 сдвигаем все знаки в право от 4 до 10 пикселей
        //$x = ($i+1)*$font_size + rand(4,7);
        $y = (($height * 2) / 3) + rand(0, 5);
        $cod[] = $letter;
        imagettftext($src, $size, rand(0, 15), $x, $y, $color, $font, $letter);
    }
    header("Content-type: image/gif");
    imagegif($src);
    return implode('', $cod);
}



function page($file, $cat, $area, $count, $size = 20, $page = 1)
{
    global $tpl;
    $page = intval($page);
    if ($page < 1) $page = 1;

    $page_count = $count > 0 ? intval(ceil($count / $size)) : 1;
    $page_prev = ($page > 1) ? $page - 1 : 1;
    $page_next = ($page < $page_count) ? $page + 1 : $page_count;

    $pager['start'] = ($page - 1) * $size;
    $pager['page'] = $page;
    $pager['size'] = $size;
    $pager['count'] = $count;
    $pager['page_count'] = $page_count;

    if ($page_count <= '1') {
        $pager['first'] = $pager['prev'] = $pager['next'] = $pager['last'] = '';
    } elseif ($page_count > '1') {
        if ($page == $page_count) {
            $pager['first'] = url_rewrite('category', array('cid' => $cat, 'eid' => $area), 1);
            $pager['prev'] = url_rewrite('category', array('cid' => $cat, 'eid' => $area), $page_prev);
            $pager['next'] = '';
            $pager['last'] = '';
        } elseif ($page_prev == '1' && $page == '1') {
            $pager['first'] = '';
            $pager['prev'] = '';
            $pager['next'] = url_rewrite('category', array('cid' => $cat, 'eid' => $area), $page_next);
            $pager['last'] = url_rewrite('category', array('cid' => $cat, 'eid' => $area), $page_count);
        } else {
            $pager['first'] = url_rewrite('category', array('cid' => $cat, 'eid' => $area), 1);
            $pager['prev'] = url_rewrite('category', array('cid' => $cat, 'eid' => $area), $page_prev);
            $pager['next'] = url_rewrite('category', array('cid' => $cat, 'eid' => $area), $page_next);
            $pager['last'] = url_rewrite('category', array('cid' => $cat, 'eid' => $area), $page_count);
        }
    }
    return $pager;
}

function get_pager($url, $param, $count, $page = 1, $size = 10)
{
    $size = intval($size);
    if ($size < 1) $size = 10;

    $page = intval($page);
    if ($page < 1) $page = 1;

    $count = intval($count);

    $page_count = $count > 0 ? intval(ceil($count / $size)) : 1;
    if ($page > $page_count) $page = $page_count;

    $page_prev = ($page > 1) ? $page - 1 : 1;
    $page_next = ($page < $page_count) ? $page + 1 : $page_count;

    $param_url = '?';
    foreach ($param as $key => $value) $param_url .= $key . '=' . $value . '&';

    $pager['url'] = $url;
    $pager['start'] = ($page - 1) * $size;
    $pager['page'] = $page;
    $pager['size'] = $size;
    $pager['count'] = $count;
    $pager['page_count'] = $page_count;

    if ($page_count <= '1') {
        $pager['first'] = $pager['prev'] = $pager['next'] = $pager['last'] = '';
    } else {
        if ($page == $page_count) {
            $pager['first'] = $url . $param_url . 'page=1';
            $pager['prev'] = $url . $param_url . 'page=' . $page_prev;
            $pager['next'] = '';
            $pager['last'] = '';
        } elseif ($page_prev == '1' && $page == '1') {
            $pager['first'] = '';
            $pager['prev'] = '';
            $pager['next'] = $url . $param_url . 'page=' . $page_next;
            $pager['last'] = $url . $param_url . 'page=' . $page_count;

        } else {
            $pager['first'] = $url . $param_url . 'page=1';
            $pager['prev'] = $url . $param_url . 'page=' . $page_prev;
            $pager['next'] = $url . $param_url . 'page=' . $page_next;
            $pager['last'] = $url . $param_url . 'page=' . $page_count;
        }
    }
    return $pager;
}

function url_rewrite($app, $params, $page = 0, $size = 0)
{
    global $CFG;
    static $rewrite = NULL;
    $act = '';
    $tid = '';
    $hid = '';

    if ($rewrite === NULL) $rewrite = intval($CFG['rewrite']);
    $args = array('aid' => 0, 'bid' => '0', 'cid' => 0, 'vid' => 0, 'eid' => '0', 'tid' => '0', 'hid' => '0');
    extract(array_merge($args, $params));
    $uri = '';
    switch ($app) {
        case 'category':
            if (empty($cid) && empty($eid)) {
                return false;
            } else {
                if ($rewrite) {
                    $uri = 'cat-' . $cid;
                    if (!empty($eid)) $uri .= '-area-' . $eid;
                    if (!empty($page)) $uri .= '-page-' . $page;
                } else {
                    $uri = 'search.php?id=' . $cid; /////////////////////////////////////// Перенаправили на search category.php?id=
                    if (!empty($eid)) $uri .= '&amp;area=' . $eid;
                    if (!empty($page)) $uri .= '&amp;page=' . $page;
                }
            }
            break;

        case 'view':
            if (empty($vid)) {
                return false;
            } else {
                $uri = $rewrite ? 'view-' . $vid : 'view.php?id=' . $vid;
            }
            break;

        case 'about':
            if (empty($aid)) {
                return false;
            } else {
                $uri = $rewrite ? 'about-' . $aid : 'about.php?id=' . $aid;
            }
            break;

        case 'help':

            if ($act == 'list' && $tid) {
                if ($rewrite) {
                    $uri = 'help-list-' . $tid;
                } else {
                    $uri = 'help.php?act=list&amp;typeid=' . $tid;
                }
            } elseif ($act == 'view' && $hid) {
                if ($rewrite) {
                    $uri = 'help-view-' . $hid;
                } else {
                    $uri = 'help.php?act=view&amp;id=' . $hid;
                }
            }
            break;
        case 'news':
            if ($act == 'list' && $tid) {
                if ($rewrite) {
                    $uri = 'news-list-' . $tid;
                } else {
                    $uri = 'news.php?act=list&amp;typeid=' . $tid;
                }
            } elseif ($act == 'view' && $hid) {
                if ($rewrite) {
                    $uri = 'news-view-' . $hid;
                } else {
                    $uri = 'news.php?act=view&amp;id=' . $hid;
                }
            }
            break;

        default:
            return false;
            break;
    }
    if ($rewrite) $uri .= '.html';
    return $uri;
}


function enddate($date)
{
    $date = round(($date > 0 ? ($date - time()) : '0') / (3600 * 24));
    if ($date > 0) {
        $day = $date;
    } elseif ($date == 0) {
        $day = '---';//Не ограничено
    } else {
        $day = '0';//Срок истек
    }
    return $day;
}

function template($file)
{
    global $CFG;

    $compiledfile = 'data/compiled/' . $file . '.php';
    $tplfile = BOARD_ROOT . 'templates/' . $CFG['tplname'] . '/' . $file . '.htm';
    if (!file_exists($compiledfile) || @filemtime($tplfile) > @filemtime($compiledfile)) {
        template_compile($tplfile, $compiledfile);
    }
    return $compiledfile;
}

function clear_caches($type = 'phpcache', $ext = '')
{
    $dirs = array();
    $tmp_dir = 'data';

    if ($type == 'phpcache') {
        $dirs = array(BOARD_ROOT . $tmp_dir . '/phpcache/');
    } elseif ($type == 'sqlchche') {
        $dirs = array(BOARD_ROOT . $tmp_dir . '/sqlcache/');
    } elseif ($type == 'compiled') {
        $dirs = array(BOARD_ROOT . $tmp_dir . '/compiled/');
    }
    $str_len = strlen($ext);
    $count = 0;

    foreach ($dirs AS $dir) {
        $folder = @opendir($dir);

        if ($folder === false) {
            continue;
        }
        while ($file = readdir($folder)) {
            if ($file == '.' || $file == '..' || $file == 'index.htm' || $file == 'index.html') {
                continue;
            }
            if (is_file($dir . $file)) {

                $pos = strrpos($file, '.');

                if ($str_len > 0 && $pos !== false) {
                    $ext_str = substr($file, 0, $pos);

                    if ($ext_str == $ext) {
                        if (@unlink($dir . $file)) {
                            $count++;
                        }
                    }
                } else {
                    if (@unlink($dir . $file)) {
                        $count++;
                    }
                }
            }
        }
        closedir($folder);
    }
    return $count;
}

function get_parent_cat()
{
    global $db, $table;

    $data = read_cache('parent_cat');
    if ($data === false) {
        $sql = "select catid,catname from {$table}category where parentid = '0' ";
        $res = $db->query($sql);
        while ($row = $db->fetchrow($res)) {
            $parent_cat[] = $row;
        }
        write_cache('parent_cat', $parent_cat);
    } else {
        $parent_cat = $data;
    }
    return $parent_cat;
}




function get_cat_children_id($catid, $type = 'int')
{
    $id ='';
    $cats = get_cat_list();
    $cat_children = $cats[$catid]['children'];
    if (is_array($cat_children)) {
        if ($type == 'int') {
            foreach ($cat_children as $child) {
                $id .= $child['id'] . ',';
            }
            $result = substr($id, 0, -1);
        } elseif ($type == 'array') {
            $result = $cat_children;
        }
    } else {
        if ($type == 'int') {
            $result = $catid;
        } elseif ($type == 'array') {
            $result = '';
        }
    }
    return $result;
}


function get_parent_area()
{
    global $db, $table;

    $data = read_cache('parent_area');
    if ($data === false) {
        $sql = "select areaid,areaname from {$table}area where parentid = '0' ";
        $res = $db->query($sql);
        while ($row = $db->fetchrow($res)) {
            $parent_area[] = $row;
        }
        write_cache('parent_area', $parent_area);
    } else {
        $parent_area = $data;
    }
    return $parent_area;
}



function get_area_info($areaid)
{
    global $db, $table;

    $data = read_cache('area_' . $areaid);
    if ($data === false) {
        $sql = "select * from {$table}area where areaid='$areaid' ";
        $area_info = $db->getrow($sql);
        write_cache('area_' . $areaid, $area_info);
    } else {
        $area_info = $data;
    }
    return $area_info;
}

function get_config()
{
    global $db, $table;

    $data = read_cache('webconfig');
    if ($data === false) {
        $sql = "select setname,value from {$table}config";
        $res = $db->query($sql);

        while ($row = $db->fetchRow($res)) {
            $config[$row['setname']] = $row['value'];
            if ($row['setname'] == 'qq' && $row['value']) {
                $config[$row['setname']] = explode('|', $row['value']);
            }
        }
        write_cache('webconfig', $config);
    } else {
        $config = $data;
    }
    return $config;
}

function get_pay()
{
    global $db, $table;
    $sql = "SELECT setname,value FROM {$table}pay";
    $res = $db->query($sql);
    while ($row = $db->fetchRow($res)) {
        $arr[$row['setname']] = $row['value'];

    }

    return $arr;
}

function get_infout()
{
    global $db, $table;
    $sql = "SELECT setname,value FROM {$table}infout";
    $res = $db->query($sql);
    while ($row = $db->fetchRow($res)) {
        $arr[$row['setname']] = $row['value'];

    }

    return $arr;
}


function get_link_list()
{
    global $db, $table;

    $result['image'] = array();
    $result['txt'] = array();

    $data = read_cache('link');
    if ($data === false) {
        $sql = "select * from {$table}link order by linkorder, id";
        $row = $db->getAll($sql);
        foreach ($row as $link) {
            if ($link['logo']) {
                $links['image'][] = $link;
            } else {
                $links['txt'][] = $link;
            }
        }
        write_cache('link', $links);
    } else {
        $links = $data;
    }
    return $links;
}


// $thumb = '' Если empty то ишем только с фото
function get_info($cat = '', $area = '', $num = '10', $protype = '', $listtype = '', $len = '20', $thumb = '')
{
    global $db, $table;

    $where = " where is_check = 1 and is_hide = 0 ";
    if (!empty($cat)) {
        $where .= " AND i.catid in ($cat)";
    }
    if (!empty($area)) {
        $where .= " AND i.areaid in ($area)";
    }
    if (empty($thumb)) {
        $where .= " and thumb != '' ";
    }

    if (!empty($protype)) {
        switch ($protype) {
            case 'pro':
                $where .= " AND is_pro >=" . time();
                break;

            case 'top':
                $where .= " AND is_top >=" . time();
                break;
        }
    }
    if (!empty($listtype)) {
        switch ($listtype) {
            case 'date':
                $order = " order by is_postdate DESC";
                break;

            case 'click':
                $order = " order by click desc, is_postdate desc ";
                break;
        }
    }
    $limit = " LIMIT 0,$num ";

    $sql = "select i.id,i.title,i.is_postdate,i.thumb,c.catid,c.catname,a.areaname from {$table}info as i left join {$table}category as c on i.catid = c.catid left join {$table}area as a on a.areaid = i.areaid $where $order $limit";
    $res = $db->query($sql);
    $info = array();
    while ($row = $db->fetchRow($res)) {
        $row['title'] = cut_str_next($row['title'], $len) ;
        $row['is_postdate'] = date('Y/m/d', $row['is_postdate']);
        $row['url'] = url_rewrite('view', array('vid' => $row['id']));
        $row['caturl'] = url_rewrite('category', array('cid' => $row['catid']));
        $row['areaurl'];
        url_rewrite('category', array('eid' => $row['areaid']));

        if (empty($row['thumb'])) { // Если пустая ссылка на фото то вставляем бланк
            $row['thumb'] = $thumb;
        }

        $info[] = $row;

    }
    return $info;
}

function get_flash()
{
    global $db, $table;
    $image = '';
    $url = '';
    $data = read_cache('flash');
    if ($data === false) {
        $sql = "select * from {$table}flash order by flaorder,id";
        $res = $db->query($sql);
        $result = array();
        while ($row = $db->fetchRow($res)) {
            $image .= $row['image'] . '|';
            $url .= $row['url'] . '|';
        }
        if (!empty($image) && !empty($url)) {
            $flash['image'] = substr($image, 0, -1);
            $flash['url'] = substr($url, 0, -1);
        }
        write_cache('flash', $flash);
    } else {
        $flash = $data;
    }
    return $flash;
}

function get_nav()
{
    global $db, $table;

    $data = read_cache('nav');
    if ($data === false) {
        $sql = "select * from {$table}nav order by navorder";
        $nav = $db->getAll($sql);
        write_cache('nav', $nav);
    } else {
        $nav = $data;
    }
    return $nav;
}


function get_info_custom($infoid)
{
    global $db, $table;

    $sql = "select a.cusid, a.cusname, a.unit, g.cusvalue from {$table}cus_value as g left join {$table}custom as a on a.cusid = g.cusid where g.infoid = '$infoid' order by a.listorder, a.cusid";
    $res = $db->query($sql);
    $cus = array();
    while ($row = $db->fetchRow($res)) {
        $arr['name'] = $row['cusname'];
        $arr['value'] = $row['cusvalue'];
        $arr['unit'] = $row['unit'];
        $cus[] = $arr;
    }
    return $cus;
}

function get_custom_info($cusid = '')
{
    global $db, $table;

    $data = read_cache('custom');
    if ($data === false) {
        $sql = "select * from {$table}custom  ";
        $res = $db->query($sql);
        while ($row = $db->fetchrow($res)) {
            $custom_info[$row[cusid]] = $row;
        }
        write_cache('custom', $custom_info);
    } else {
        $custom_info = $data;
    }
    return $custom_info[$cusid];
}

//function get_cat_custom($catid)
//{
//    global $db, $table;
//
//    $cat_info = get_cat_info($catid);
//    $parentid = $cat_info['parentid'];
//    $search_id = $parentid ? $parentid : $catid;
//
//    $data = read_cache('cat_custom_' . $catid);
//    if ($data === false) {
//        $sql = "select cusid, cusname, custype, value, search, listorder, unit from {$table}custom  where  catid = '$search_id' order by catid, listorder asc";
//        $cat_custom = $db->getall($sql);
//        write_cache('cat_custom_' . $catid, $cat_custom);
//    } else {
//        $cat_custom = $data;
//    }
//    return $cat_custom;
//}

//function cat_post_custom($catid, $id = '')
//{
//    global $db, $table;
//
//    if (empty($catid)) if (empty($catid)) return array();
//    if ($id) {
//        $sql = "select c.*,v.* from {$table}custom as c left join {$table}cus_value as v on c.cusid=v.cusid left join {$table}info as i on i.id=v.infoid where i.id='$id' ";
//        $res = $db->query($sql);
//        while ($row = $db->fetchrow($res)) {
//            $info_cus[$row[cusid]] = $row;
//        }
//    }
//    $customs = get_cat_custom($catid);
//    if (empty($customs)) return false;
//
//    foreach ($customs as $key => $val) {
//        $info_cus_value = $info_cus[$val['cusid']];
//
//        if ($val['custype'] == 0) {
//            $val['html'] .= "<input name='cus_value[$val[cusid]]' type='text' value='" . htmlspecialchars($val['cusvalue']) . "' size='20' /> " . $val[unit];
//        } elseif ($val['custype'] == 1) {
//            $val['html'] .= '<select name="cus_value[' . $val['cusid'] . ']">';
//            $val['html'] .= '<option value="">Выберите</option>';
//            $cusvalues = explode("\n", $val['value']);
//            foreach ($cusvalues as $opt) {
//                $opt = trim(htmlspecialchars($opt));
//                $val['html'] .= ($info_cus_value['cusvalue'] != $opt) ?
//                    '<option value="' . $opt . '">' . $opt . '</option>' :
//                    '<option value="' . $opt . '" selected="selected">' . $opt . '</option>';
//            }
//            $val['html'] .= "</select> $val[unit]";
//        } elseif ($val['custype'] == 2) {
//            $cusvalues = explode("\n", $val['value']);
//            $info_cusvalue = explode(",", $info_cus_value['cusvalue']);
//
//            foreach ($cusvalues as $opt) {
//                $opt = trim(htmlspecialchars($opt));
//                $a = in_array($opt, $info_cusvalue) ? "checked=checked" : '';
//                $val['html'] .= ' <input type="checkbox" value="' . $opt . '" name="cus_value[' . $val['cusid'] . '][]" ' . $a . ' >' . $opt;
//            }
//        }
//        $result[$val['cusid']]['cusname'] = $val['cusname'];
//        $result[$val['cusid']]['html'] = $val['html'];
//        $result[$val['cusid']]['unit'] = $val['unit'];
//    }
//    return $result;
//}

function cat_search_custom($catid = '')
{
    global $db, $table;

    if (empty($catid)) return array();
    $customs = get_cat_custom($catid);
    foreach ($customs as $row) {
        if ($row['search'] == '0') continue;

        if ($row['custype'] == '1' || $row['custype'] == '2') {
            $row['value'] = str_replace("\r", '', $row['value']);
            $options = explode("\n", $row['value']);
            $cusvalue = array();
            foreach ($options as $opt) {
                $cusvalue[$opt] = $opt;
            }
            $custom[] = array(
                'id' => $row['cusid'],
                'cusname' => $row['cusname'],
                'options' => $cusvalue,
                'search' => $row['search'],
                'type' => $row['custype']);
        } else {
            $custom[] = array(
                'id' => $row['cusid'],
                'cusname' => $row['cusname'],
                'search' => $row['search'],
                'type' => $row['custype']);
        }
    }
    if ($custom) {
        foreach ($custom as $cus) {
            if ($cus['type'] == '0') {
                if ($cus['search'] == '2') {
                    $cus['html'] = '<input name=custom[$cus[id]][from] value="" type=text size=5 maxlength=5 > - <input name=custom[$cus[id]][to] type=text value="" size=5 maxlength=5 >';
                } else {
                    $cus['html'] = '<input name="custom[<?=$cus[id]?>]"  type="text" size="15" maxlength="120" />';
                }
            } elseif ($cus['type'] == '1') {
                $cus['html'] = "<select name=custom[$cus[id]]>
				<option value=0>Выберите</option>";
                foreach ($cus['options'] as $opt) {
                    $cus['html'] .= "<option value=$opt>$opt</option>";
                }
                $cus['html'] .= '</select>';
            } elseif ($cus['type'] == '2') {
                foreach ($cus['options'] as $opt) {
                    $opt = trim(htmlspecialchars($opt));
                    $cus['html'] .= ' <input type="checkbox" value="' . $opt . '" name="custom[' . $cus[id] . '][]" >' . $opt;
                }
            }
            $result[] = $cus;
        }
    }
    return $result;
}

function get_domain()
{

    $protocol = http();

    if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
    } elseif (isset($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
    } else {

        if (isset($_SERVER['SERVER_PORT'])) {
            $port = ':' . $_SERVER['SERVER_PORT'];

            if ((':80' == $port && 'http://' == $protocol) || (':443' == $port && 'https://' == $protocol)) {
                $port = '';
            }
        } else {
            $port = '';
        }

        if (isset($_SERVER['SERVER_NAME'])) {
            $host = $_SERVER['SERVER_NAME'] . $port;
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $host = $_SERVER['SERVER_ADDR'] . $port;
        }
    }
    return $protocol . $host;
}

function url()
{

    $PHP_SELF = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : $_SERVER['ORIG_PATH_INFO']);

    $curr = strpos($PHP_SELF, 'admin/') !== false ?
        preg_replace('/(.*)(admin)(\/?)(.)*/i', '\1', dirname($PHP_SELF)) :
        dirname($PHP_SELF);

    $root = str_replace('\\', '/', $curr);

    if (substr($root, -1) != '/') {
        $root .= '/';
    }

    return get_domain() . $root;
}

function ads_list($placeid = '')
{
    global $db, $table, $CFG;

    if (empty($placeid)) return '';
    $weburl = $CFG['weburl'];

    $sql = "select a.*,p.width,p.height from {$table}ads as a left join {$table}ads_place as p on a.placeid=p.placeid where a.placeid = '$placeid' ";
    $res = $db->query($sql);

    $ads = array();
    while ($row = $db->fetchrow($res)) {
        $adscode = '';
        switch ($row['adstype']) {
            case '1':
                $adscode = "<a href=$row[adsurl] target=\"_blank\">" . nl2br(htmlspecialchars(addslashes($row['adscode']))) . "</a>";
                break;

            case '2':
                $src = (strpos($row['adcode'], 'http://') === false && strpos($row['adcode'], 'https://') === false) ? $weburl . "/$row[adscode]" : $row['adscode'];
                $adscode = "<a target=_blank href=" . $row['adsurl'] . " >" . "<img src=" . $src . " border=0 width= " . $row['width'] . " height=" . $row['height'] . "alt=" . $row['adsname'] . " /></a>";
                break;

            case '3':
                $src = (strpos($row['adscode'], 'http://') === false && strpos($row['adscode'], 'https://') === false) ? $weburl . '/' . $row['adscode'] : $row['adscode'];
                $adscode = '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,29,0" width="' . $row['width'] . '" height="' . $row['height'] . '"> <param name="movie" value="' . $src . '"><param name="quality" value="high"><embed src="' . $src . '" quality="high" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash" width="' . $row['width'] . '" height="' . $row['height'] . '"></embed></object>';
                break;

            case '4':
                $adscode = $row['adscode'];
                break;
        }
        $ads[] = $adscode;
    }
    include template('ads');
}

function member_info($userid = '')
{
    global $db, $table;
    $sql = "select * from {$table}member where userid = '$userid' ";
    $row = $db->getrow($sql);
    return $row;
}

function template_compile($tplfile, $tplcachefile)
{
    $str = file_get_contents($tplfile);
    $str = template_parse($str);
    $strlen = file_put_contents($tplcachefile, $str);
    @chmod($tplcachefile, 0777);
    return $strlen;
}

function template_parse($tpl)
{
    global $compress_template;

    $tpl = preg_replace("/([\n\r]+)\t+/s", "\\1", $tpl);
    $tpl = preg_replace("/\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $tpl); /// <!--{template footer}--> >меняем на> {template footer} и любые операторы можно в это спрятать
    $tpl = preg_replace("/\{template\s+(.+)\}/", "\n<?php include template(\\1); ?>\n", $tpl); /// {template footer} >меняем на> include template(footer);
    $tpl = preg_replace("/\{include\s+(.+)\}/", "\n<?php include \\1; ?>\n", $tpl);
    $tpl = preg_replace("/\{php\s+(.+)\}/", "\n<?php \\1?>\n", $tpl);
    $tpl = preg_replace("/\{if\s+(.+?)\}/", "<?php if(\\1) { ?>", $tpl);
    $tpl = preg_replace("/\{else\}/", "<?php } else { ?>", $tpl);
    $tpl = preg_replace("/\{elseif\s+(.+?)\}/", "<?php } elseif (\\1) { ?>", $tpl);
    $tpl = preg_replace("/\{\/if\}/", "<?php } ?>", $tpl);

// <!--{for $d = 1; $d <= 4; $d++ }-->   <!--{endfor}--> Функция добавляет цикл
    $tpl = preg_replace("/\{for\s+(.+?)\}/", "<?php for (\\1) { ?>", $tpl);
    $tpl = preg_replace("/\{endfor\}/", "<?php } ?>", $tpl);

    $tpl = preg_replace("/\{loop\s+(\S+)\s+(\S+)\}/", "<?php if(is_array(\\1)) foreach(\\1 AS \\2) { ?>", $tpl);
    $tpl = preg_replace("/\{loop\s+(\S+)\s+(\S+)\s+(\S+)\}/", "\n<?php if(is_array(\\1)) foreach(\\1 AS \\2 => \\3) { ?>", $tpl);
    $tpl = preg_replace("/\{\/loop\}/", "\n<?php } ?>\n", $tpl);
    $tpl = preg_replace("/\{([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\(([^{}]*)\))\}/", "<?php echo \\1;?>", $tpl);
    $tpl = preg_replace("/\{\\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\(([^{}]*)\))\}/", "<?php echo \\1;?>", $tpl);
    $tpl = preg_replace("/\{(\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\}/", "<?php echo \\1;?>", $tpl);
    $tpl = preg_replace("/\{(\\$[a-zA-Z0-9_\[\]\'\"\$\x7f-\xff]+)\}/es", "addquote('<?php echo \\1;?>')", $tpl);
    $tpl = preg_replace("/\{([A-Z_\x7f-\xff][A-Z0-9_\x7f-\xff]*)\}/s", "<?php echo \\1;?>", $tpl);
    $tpl = "<?php if(!defined('BOARD'))die('Access Denied'); ?>" . $tpl;

    if ($compress_template) {
//        $tpl = preg_replace("/\<\!\-\-(.+?)\-\-\>/s", "", $tpl); // Удалим коментарии, старая версия
        $tpl = preg_replace("#(?!<!--\[if IE\]>)(?!<\!\[endif\]-->)(?!счетчики)<!--(.*?)-->#", "", $tpl); // Удалим коментарии, не удалять для IE <!--[if IE]><![endif]--> и счетчики

        //Убираем переносы строк и лишние пробелы.
        $tpl = str_replace("\r", "", $tpl);
        $tpl = str_replace("\n", "", $tpl);
        $tpl = preg_replace("/(\s){2,}/", " ", $tpl);
    }

    return $tpl;
}

function addquote($var)
{
    return str_replace("\\\"", "\"", preg_replace("/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\]/s", "['\\1']", $var));
}

if (!function_exists('file_get_contents')) {
    function file_get_contents($file)
    {
        if (($fp = @fopen($file, 'rb')) === false) {
            return false;
        } else {
            $fsize = @filesize($file);
            if ($fsize) {
                $contents = fread($fp, $fsize);
            } else {
                $contents = '';
            }
            fclose($fp);

            return $contents;
        }
    }
}

if (!function_exists('file_put_contents')) {
    define('FILE_APPEND', 'FILE_APPEND');

    function file_put_contents($file, $data, $flags = '')
    {
        $contents = (is_array($data)) ? implode('', $data) : $data;

        if ($flags == 'FILE_APPEND') {
            $mode = 'ab+';
        } else {
            $mode = 'wb';
        }

        if (($fp = @fopen($file, $mode)) === false) {
            return false;
        } else {
            $bytes = fwrite($fp, $contents);
            fclose($fp);

            return $bytes;
        }
    }
}

function read_cache($filename)
{
    $data = '';
    $result = array();
    if (!empty($result[$filename])) {
        return $result[$filename];
    }
    $filepath = BOARD_ROOT . 'data/phpcache/' . $filename . '.php';
    if (file_exists($filepath)) {
        include_once($filepath);
        $result[$filename] = $data;
        return $result[$filename];
    } else {
        return false;
    }
}

function write_cache($filename, $val)
{
    $filepath = BOARD_ROOT . 'data/phpcache/' . $filename . '.php';
    $content = "<?php\r\n";
    $content .= "\$data = " . var_export($val, true) . ";\r\n";
    $content .= "?>";
    file_put_contents($filepath, $content, LOCK_EX);
}

function onlyarea($postarea)
{
    global $db, $table, $CFG;

    if (!empty($CFG['onlyarea'])) {
        if (!strstr($postarea, $CFG['onlyarea'])) {
            showmsg("С вашего IP <font color=red>" . $CFG[onlyarea] . "</font> запрещено добавлять объявления");
        }
    }
}


// Проверяем капчю true если ошибка
function check_code($checkcode)
{
    $ret = false;
    $chkcode = $_SESSION['chkcode'];
    if (empty($chkcode) || $chkcode != $checkcode) $ret = true;

    return $ret;
}

// Проверяем на наличие запрещенных слов в тексте true если найдено запрещенное слово
function check_words($who = array())
{
    global $CFG;
    $ret = false;

    if (!empty($CFG['banwords'])) {
        $ban = explode('|', $CFG['banwords']);
        $count = count($ban);
        for ($i = 0; $i < $count; $i++) {
            foreach ($who as $val) {
                if (strstr($val, $ban[$i])) {
                   // showmsg('В объявлении обнаружены запрещенные администрацией слова');
                   $ret = true;
                }
            }
        }
    }
    return $ret;
}


function login($username, $password)
{
///    global $db, $table, $CFG; ненужно зря обявили

    if (check_user($username, $password) > 0) {
        set_session($username);
        return true;
    } else {
        set_session();
        return false;
    }
}

function check_user($username, $password = '')
{
    global $db, $table, $CFG;
    if ($password == '') {
        $sql = "select userid FROM {$table}member WHERE username = '$username'";
    } else {
        $sql = "select userid FROM {$table}member WHERE username = '$username' AND password ='$password'";
    }
    return $db->getone($sql);
}

function logout()
{
    set_session();
}

function set_session($username = '')
{
    global $db, $table, $CFG;

    if (empty($username)) {
        $_SESSION['userid'] = '';
        $_SESSION['username'] = '';
        $_SESSION['password'] = '';
    } else {
        $sql = "SELECT userid, password, email , lastlogintime , sendmailtime FROM {$table}member WHERE username='$username' LIMIT 1";
        $row = $db->getrow($sql);

        if ($row) {
            $_SESSION['userid'] = $row['userid'];
            $_SESSION['username'] = $username;
            $_SESSION['password'] = $row['password'];
        }

        $time = time();
        $ip = get_ip();
        $db->query("UPDATE {$table}member SET lastlogintime='$time',lastloginip='$ip' where username = '$username' ");
    }
}


function register($username, $password, $email)
{
    global $db, $table, $CFG;

    if (check_user($username) > 0) {
        showmsg("Логин $username уже есть в базе данных");
    }

    $sql = "select userid FROM {$table}member  WHERE email = '$email'";
    if ($db->getone($sql) > 0) {
        showmsg("E-mail $email уже есть в базе данных");
    }

    $time = time();
    $ip = get_ip();
    $sql = "INSERT INTO {$table}member (username,password,email,registertime,registerip,lastlogintime) VALUES ('$username','$password','$email','$time','$ip','$time')";
    $res = $db->query($sql);

    if ($res) {
        set_session($username);
        return true;
    } else {
        set_session();
        return false;
    }

}

// Генерируем пароль на определенной длины
function gen_new_char ($char_length){
    $letters = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'k', 'm', 'n', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '2', '3', '4', '5', '6', '7', '9');
    for ($i = 0; $i < $char_length; $i++) {
        $letter = $letters[rand(0, sizeof($letters) - 1)];
        $cod[] = $letter;
    }
    $new_pass = implode('', $cod);
    return $new_pass;
}


function register_hide($email, $password)
{
    global $db, $table;

    $sql = "select userid FROM {$table}member  WHERE email = '$email'";
    if ($db->getone($sql) > 0) {
        showmsg("E-mail $email уже есть в базе данных");

        // Вернуть пароль юзера
    } else {
        $time = time();
        $ip = get_ip();
        $sql = "INSERT INTO {$table}member (password,email,registertime,registerip,lastlogintime) VALUES ('$password','$email','$time','$ip','$time')";
        $res = $db->query($sql);

        if ($res) {
    // Удачьно зарегестрировался
        }


    }

}

function less_to_css($less_files, $css_file, $file_list_json = '')
{
    global $less_compiler_enable;

    if ($file_list_json == '') {
        $file_list_json = $css_file . ".json";
    }

    $compiled_file_list =  json_decode(file_get_contents($file_list_json));

    if ($less_compiler_enable) { // Если компилятор включен

        function compile_file($less_files)
        { // Сжать Less файлы
            require_once BOARD_ROOT . "include/less.php/Less.php";
            global $compress_css;
            $options = array(
                "compress" => $compress_css,
                "cache_method" => False
            );
            $parser = new Less_Parser($options);
            if (is_array($less_files)) { // Если масив
                foreach ($less_files as $less) {
                    $parser->parseFile(BOARD_ROOT . $less);
                }
            } else {
                $parser->parseFile(BOARD_ROOT . $less_files);
            }
            $css = $parser->getCss();

            return $css;
        }

        function file_dir($file_name) // Директория файла
        {
            $patch_info = pathinfo($file_name);
            return $patch_info[dirname];
        }

        function last_update($patch, $file_type = "*.less") // Максимальная дата файла
        {
            $max_time = 0;
            if (is_dir($patch)) {
                foreach (glob($patch . '/' . $file_type) as $filename) {
                    if (is_file($filename)) {
                        $file_time = filemtime($filename);
                        if ($file_time > $max_time) $max_time = $file_time;
                    }
                }
            } else {
                $max_time = filemtime($patch);
            }
            return $max_time;
        }


        if ($compiled_file_list[2] != $less_files) { // Если списки less не соответствуют
            // Компилируем
            $css = compile_file($less_files);
            $path_parts = pathinfo($css_file); // разберем имя css файла
            $css_file_save =  $path_parts['dirname'] . "/" . $path_parts['filename'] . "." . md5($css) . "." . $path_parts['extension']; // имя файла + md5
            file_put_contents($css_file_save, $css); // Сохраняем скомпилированный файл

            // Сохраняем новый список  0 Скомпилированный файл, 1 оригинальное имя файла, 2 json файл - список оригинльных файлов
            $file_save_json = array($css_file_save, $css_file, $less_files);
            file_put_contents($file_list_json, json_encode($file_save_json));
            $compiled_file_list = $file_save_json; // для return

        } else { // Если списки json одинаковые
            $g_last_update = 0; // Максимальная дата обновления во всех каталогах less
            if (is_array($less_files)) { // Если масив less файлов
                foreach ($less_files as $less) {
                    $last_update_file_dir = last_update(file_dir($less), "*.less"); // Находм максимальную дату обнавления в каталоге файла less
                    if ($last_update_file_dir > $g_last_update) {
                        $g_last_update = $last_update_file_dir;
                    } // Если максимальная дата больше чем в коталоге less файла
                }
            } else {
                $g_last_update = last_update(file_dir($less_files), "*.less"); // Находм максимальную дату обнавления
            }

            $css_last_update = last_update($compiled_file_list[0]); // Это не массив маска не нужна

            if ($g_last_update > $css_last_update) {
                $css = compile_file($less_files);
                $path_parts = pathinfo($css_file); // разберем имя css файла
                $css_file_save = $path_parts['dirname'] . "/" . $path_parts['filename'] . "." . md5($css) . "." . $path_parts['extension']; // имя файла + md5
                file_put_contents($css_file_save , $css); // Сохраняем скомпилированный файл

                // Сохраняем новый список  0 Скомпилированный файл, 1 оригинальное имя файла, 2 json файл
                $file_save_json = array($css_file_save, $css_file, $less_files);
                file_put_contents($file_list_json, json_encode($file_save_json));
                $compiled_file_list = $file_save_json; // для return
            }
        }

    }
    return $compiled_file_list[0];
}

/** JS Компрессор
 * @param $array_js_files массив js исходников
 * @param $compress_js_file имя куда сжимать файл
 * @param $file_list_json файл список исходных JS
 * @return $compress_js_file Имя сжатого файла
 */
function compress_js($array_js_files, $compress_js_file, $file_list_json = '')
{
    global $js_compressor_enable, $compress_js;

    if ($file_list_json == '') {
        $file_list_json = $compress_js_file . '.json';
    };

    // Сохранений массив
    $compiled_file_list = json_decode(file_get_contents($file_list_json));

    if ($js_compressor_enable) {
        require_once(BOARD_ROOT . 'include/jsmin-1.1.2.php');

// Если сохранений массив не равен новому, компилируем заново
        if ($compiled_file_list[2] != $array_js_files) {
            $js = "";
            foreach ($array_js_files as $file) {
                if ($compress_js) {
                    $js .= JSMin::minify(file_get_contents($file));
                } else {
                    $js .= file_get_contents($file);
                }
            }

            $path_parts = pathinfo($compress_js_file); // разберем имя js файла
            $js_file_save = $path_parts['dirname'] . "/" . $path_parts['filename'] . "." . md5($js) . "." . $path_parts['extension']; // имя файла + md5
            file_put_contents($js_file_save , $js); // Сохраняем скомпилированный файл

            // Сохраняем новый список  0 Имя скомпилированного файла, 1 оригинальное имя файла, 2 Список исходников
            $file_save_json = array($js_file_save,  $compress_js_file, $array_js_files );
            file_put_contents($file_list_json, json_encode($file_save_json));
            $compiled_file_list = $file_save_json; // для return

        } else { // Если список совпадает но есть обновленные файлы, все равно сжимаем
            $js_last_update = 0; // Максимальная дата исходных файлов
            foreach ($array_js_files as $file) {
                if (is_file($file)) {
                    $file_time = filemtime($file);
                    if ($file_time > $js_last_update) $js_last_update = $file_time;
                }
            }
            $compress_last_update = filemtime($compiled_file_list[0]); // Находим дату сжатого файла 0 Имя скомпилированного файла
            if ($js_last_update > $compress_last_update) { // Если есть старше (обновленные) js файлы то сжимаем за ново
                $js = "";
                foreach ($array_js_files as $file) {
                    if ($compress_js) {
                        $js .= JSMin::minify(file_get_contents($file));
                    } else {
                        $js .= file_get_contents($file);
                    }
                }

                $path_parts = pathinfo($compress_js_file); // разберем имя js файла
                $js_file_save = $path_parts['dirname'] . "/" . $path_parts['filename'] . "." . md5($js) . "." . $path_parts['extension']; // имя файла + md5
                file_put_contents($js_file_save , $js); // Сохраняем скомпилированный файл

                // Сохраняем новый список  0 Имя скомпилированного файла, 1 оригинальное имя файла, 2 Список исходников
                $file_save_json = array($js_file_save,  $compress_js_file, $array_js_files );
                file_put_contents($file_list_json, json_encode($file_save_json));
                $compiled_file_list = $file_save_json; // для return
            }
        }
    }
    return $compiled_file_list[0];
}

/* --------------------function c index.php-------------------- */

// Одномерный масив в список HTML, цифровой
function array_to_ol($var){
    $out = '<ol>';
    foreach($var as $v){ $out .= '<li>' . $v . '</li>';  }
    return $out.'</ol>';
}

/* --------------------function c post.php-------------------- */
//

// выбираем шаблон для каталога по дефолту post_tpl_default
function select_post_form ($id_category){
    $cat_list = get_cat_list();
   if (empty($cat_list[$id_category]['tpl_cat'])) {
       $tpl_post_cat = 'post_tpl_default';
   } else {
       $tpl_post_cat =  $cat_list[$id_category]['tpl_cat'];
   }
    return $tpl_post_cat;
}

/////////////////////////////////////////////// Проверка переменных
function flag_reg($post_var, $reg_ex)
{ // Соответствует REG то false (можно пройти)
    $matches = array();
    if (!empty($post_var)) {
        preg_match($reg_ex, $post_var, $matches);

        if ($matches[0] == $post_var)
            {return false;} // Ве соответствует нет флага
        else
            {return true;}
    } return false; // Ве нет информации нет флага
}

function flag_null($post_var)
{ // Если ноль знаков то ставим флаг True нельзя пройти
    if (empty($post_var))
    {return true;}
    else
    {return false;}
}

function flag_strlen($post_var, $min_count, $max_count)
{ // Ели водит в рамки то можно пройти (false) нет флага
    $post_var_len = mb_strlen($post_var);
    if (!empty($post_var_len)) {
        if (
            ($post_var_len >= $min_count) and ($post_var_len <= $max_count)
        ) {return false;}
        else {return true;}
    } return false; // Ве нет информации нет флага
}
/////////////////////////////////////////////////////////////

function reg_post($post_var, $reg_ex)
{ // Если переменная соответствует регулярке false - все в порядке, нет флага
    $matches = array();
    preg_match($reg_ex, $post_var, $matches);
    if ($matches[0] == $post_var)
        {return false;}
        else
        {return true;}
}

/////////////////////////////////////////////////////////////
//  Если ошибка ставим флаг True
function check_nzz($nzz){
    if (flag_reg($nzz,"/[A-Za-z0-9]{15}/") or flag_strlen($nzz,15,15) or flag_null($nzz))
    {return true;}
    else
    {return false;}
}


//////////////////////////////////////////////////////////// Зашита от sql инжекции
function no_injection($str='') {
    if (get_magic_quotes_gpc()) { // если magic_quotes_gpc включена - используем stripslashes
        $str = stripslashes($str);
    }
    $str = mysql_real_escape_string($str);
    $str = trim($str);
    $str = htmlspecialchars($str);
    return $str;
}

/////////////////////////////////////////////////////////// !!!ДЛЯ ПОЧТЫ utf8 кодировки в загаловках, экранировать обязательно

function base64mail ($text) {
   return  "=?UTF-8?B?".base64_encode($text)."?=" ;
}

////////////////////////// Список валюты
function  get_money_list() {

    $money_list = array();

    $money_list[] = 'LAR';
    $money_list[] = 'USD';
    $money_list[] = 'EUR';
    return $money_list;
}


function  money_options($selectid = '') {
    $money = get_money_list();
    $option ='';
    foreach ($money as $money_list) {
        $option .= "<option value=$money_list";
        $option .= ($selectid == $money_list) ? " selected='selected'" : '';
        $option .= ">$money_list</option>";
    }
    return $option;
}

////////////////////////////// Создать или проверить директорию ПРОМО
function is_temp_dir (){
    global $CFG, $INF;
    $index_html = '<!DOCTYPE html><html><body bgcolor="#FFFFFF"></body></html>';

    // Куда сохраняем временные файлы фото// TEMP директория
    $temp_dir =  $CFG['temp_dir']; ////////////// ?? ставить ли в начало BOARD_ROOT .
    if (!is_dir($temp_dir)) {
        if (@mkdir(rtrim($temp_dir, '/'), 0777)) @chmod($temp_dir, 0777);
    }
    if (!file_exists($temp_dir . 'index.html')) file_put_contents($temp_dir . 'index.html', $index_html);

    return $temp_dir;

}

function is_usr_dir ($id_user){
    global $CFG, $INF;
    $index_html = '<!DOCTYPE html><html><body bgcolor="#FFFFFF"></body></html>';

// Основная директория
    $dir =  'user/'; /////////////  ?? ставить ли в начало BOARD_ROOT .
    if (!is_dir($dir)) {
        if (@mkdir(rtrim($dir, '/'), 0777)) @chmod($dir, 0777);
    }
    if (!file_exists($dir . 'index.html')) file_put_contents($dir . 'index.html', $index_html);

    $user_id_uname = 'U' . str_pad($id_user, $INF['num_zeros_user'], '0', STR_PAD_LEFT); // Имя юзера с нулями
    $dir_user = $dir . $user_id_uname . '/';
    if (!is_dir($dir_user)) {
        if (@mkdir(rtrim($dir_user, '/'), 0777)) @chmod($dir_user, 0777);
    }
    if (!file_exists($dir_user . 'index.html')) file_put_contents($dir_user . 'index.html', $index_html);

    return $dir_user;

}

function is_promo_dir( $id_user, $id_promo) {
// Подготовим директории
// Text index.htm файла
    global $CFG, $INF;
    $index_html = '<!DOCTYPE html><html><body bgcolor="#FFFFFF"></body></html>';

// Основная директория
    $dir =  'user/'; /////////////  ?? ставить ли в начало BOARD_ROOT .
    if (!is_dir($dir)) {
        if (@mkdir(rtrim($dir, '/'), 0777)) @chmod($dir, 0777);
    }
    if (!file_exists($dir . 'index.html')) file_put_contents($dir . 'index.html', $index_html);

// Директория юзеров
    $dir_user = is_usr_dir($id_user);

// Директория данных юзера обявлений $ads_id
    $ads_id_uname = 'F' . str_pad($id_promo , $INF['num_zeros_promo'], '0', STR_PAD_LEFT); // Имя обявления с нулями
    $dir_user_promo = $dir_user . $ads_id_uname . '/'; // Директория обявления
    if (!is_dir($dir_user_promo)) // Создаем директорию файлов обявления юзера
    {
        if (@mkdir(rtrim($dir_user_promo, '/'), 0777)) @chmod($dir_user_promo, 0777);
    }
    if (!file_exists($dir_user_promo . 'index.html')) file_put_contents($dir_user_promo . 'index.html', $index_html);

    return $dir_user_promo;
}

////////////////////////////////////////////////////////// Обрезать для показа в списке промо


function get_background() //  Список всех под категорий по catid на будущее
{
    global $db, $table;
    static $background = NULL;
    if ($background === NULL) {
        $data = read_cache('background');
        if ($data === false) {
            $sql = "select * from {$table}background  ORDER BY id_background ASC ";
            $res_1 = $db->getAll($sql);
            $background = array();
            foreach ($res_1 as $row_1) {
                $background[$row_1['id_background']] = $row_1['background'];
            }
            write_cache('background', $background);
        } else {
            $background = $data;
        }
    }
    return $background;
}

function get_normalize_index( $row_tpl ) { // Вычисляем нормальные поля для индекса

    global $CFG, $INF, $background ;

    /// Вычисляем сколько дней до возможности поднять
    $row_tpl['css'] = ''; // По дефолту стиля нет

    $row_tpl['vip_dey'] =  $row_tpl['is_vip'] - time();
    $row_tpl['vip_dey'] = ceil($row_tpl['vip_dey'] / 86400);
    if ($row_tpl['vip_dey'] < 0) { // Если меньше нуля по любому ноль
        $row_tpl['vip_dey'] = 0;
    } else {
        $row_tpl['css'] = $background[$row_tpl['id_background']];
    }

    $row_tpl['show_id']= str_pad($row_tpl['id'], $INF['num_zeros_promo'], '0', STR_PAD_LEFT);


    $row_tpl['title'] = cut_str_next($row_tpl['title'],  20); // $len 10 длина текста
    $row_tpl['content'] = cut_str_next($row_tpl['content'], 120); // $len 10 длина текста
    $row_tpl['is_postdate'] = date('Y/m/d', $row_tpl['is_postdate']);

    if (empty($row_tpl['thumb'])) { // Если пустая ссылка на фото то вставляем бланк
        $row_tpl['thumb'] = $CFG['image_blank'];// $thumb; /// Пка ноль !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    }

    return $row_tpl;
}



function get_normalize_tpl_default( $row_tpl ) { // Вычисляем нормальные поля дя списка обявлений

    global $CFG, $INF, $background ;

    /// Вычисляем сколько дней до возможности поднять
    $row_tpl['css'] = ''; // По дефолту стиля нет
    $row_tpl['hide'] = ''; // По дефолту стиля нет
    if ( !empty($row_tpl['is_hide']) ) { // Если Спрятали
        $row_tpl['hide'] = 'promo_hide';
    }

    $row_tpl['up_dey'] = ( $row_tpl['is_postdate'] - time() ) + ( ((int)$CFG['promo_up_dey'])  * 86400 ) - 1;
    $row_tpl['up_dey'] = ceil($row_tpl['up_dey'] / 86400);
    if ($row_tpl['up_dey'] < 0) { // Если меньше нуля по любому ноль
        $row_tpl['up_dey'] = 0;
    }

    $row_tpl['top_dey'] =  $row_tpl['is_top'] - time();
    $row_tpl['top_dey'] = ceil($row_tpl['top_dey'] / 86400);
    if ($row_tpl['top_dey'] < 0) { // Если меньше нуля по любому ноль
        $row_tpl['top_dey'] = 0;
    }

    $row_tpl['vip_dey'] =  $row_tpl['is_vip'] - time();
    $row_tpl['vip_dey'] = ceil($row_tpl['vip_dey'] / 86400);
    if ($row_tpl['vip_dey'] < 0) { // Если меньше нуля по любому ноль
        $row_tpl['vip_dey'] = 0;
    } else {
        if  ( empty($row_tpl['is_hide']) ) { // Если не спрятана
            $row_tpl['css'] = $background[$row_tpl['id_background']];
        }
    }

    $row_tpl['show_id']= str_pad($row_tpl['id'], $INF['num_zeros_promo'], '0', STR_PAD_LEFT);
    $row_tpl['title_cut'] = cut_str_next($row_tpl['title'], 30); // $len 10 длина текста
    $row_tpl['content_cut'] = cut_str_next($row_tpl['content'], 320); // $len 10 длина текста

    $row_tpl['content_htm'] = str_replace("\r", "", $row_tpl['content']); // удаляем переводы строки
    $row_tpl['content_htm'] = str_replace("\n","<br />", $row_tpl['content_htm']); // удаляем переводы строки

    $row_tpl['is_postdate'] = date('Y/m/d', $row_tpl['is_postdate']);



    if (empty($row_tpl['thumb'])) { // Если пустая ссылка на фото то вставляем бланк
        $row_tpl['thumb'] = $CFG['image_blank'];// $thumb; /// Пка ноль !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    }

    $row_tpl['phone_url'] = empty($row_tpl['phone']) ? '' : '<img src="do.php?act=show&num='.encrypt(phone_format($row_tpl['phone']), $CFG['crypt']).'" align="absbottom">';
    $row_tpl['email_url'] = empty($row_tpl['email']) ? '' : '<img src="do.php?act=show&num='.encrypt($row_tpl['email'], $CFG['crypt']).'" align="absbottom">';

    return $row_tpl;
}

function redirect_html($redirect_url){
    # Скрипт редиректа в переменую удаляем джавой историю
    $f_redirect_html = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN"><html><head>' . "\n";
    $f_redirect_html .= '<script type="text/javascript"> location.replace("'.$redirect_url.'"); </script>' . "\n";
    $f_redirect_html .= '<noscript><meta http-equiv="refresh" content="0; url='.$redirect_url.'"></noscript>' . "\n";
    $f_redirect_html .= '</head><body>Вам на <a href="'.$redirect_url.'">новое местечко</a>.</body></html>' . "\n";
    return $f_redirect_html;
}

// Работа с фото имя сжатой фото
function get_image_thumb ($image_name){
    $image_name = preg_replace("/\.jpg/", "", $image_name); // удалм расширение
    $image_name = $image_name . '.t.jpg'; // имя тумбуса

    return $image_name;
}
// Удалить директорию
function removeDirectory($dir) {
    if ($objs = glob($dir."/*")) {
        foreach($objs as $obj) {
            is_dir($obj) ? removeDirectory($obj) : unlink($obj);
        }
    }
    rmdir($dir);
}

///// Форматируем номер телефона
function phone_format($phone){
    $plus = ($phone[0] == '+'); // есть ли +
    $phone = preg_replace('/\D/', '', $phone); // убираем все знаки кроме цифр
    $count = str_len($phone); // Число цифр в строке
    $phone = strrev($phone); // Переворачиваем строку
// Как выводить
   if ( $count <= 2 )
    { $regex = "$1"; }
   if ( $count == 3 or $count == 4 )
    { $regex = "$1-$2"; }
   if ( $count == 5 or $count == 6 )
    { $regex = "$1-$2-$3"; }
   if ( $count == 7 or $count == 8 or $count == 9 )
    { $regex = "$1-$2-$3)$4("; }
   if ( $count == 10 or $count == 11 or $count == 12 )
    { $regex = "$1-$2-$3)$4($5"; }
   if ( $count > 12 )
    { $regex = "$1-$2-$3)$4($5"; }
// Форматируем строку
    $phone = preg_replace( "/([\d]{1,2})([\d]{0,2})([\d]{0,2})([\d]{0,3})([\d]{0,10})/" ,$regex , $phone);
    return ($plus ? '+' : '') . strrev($phone); // Добавляем + в начало если есть и обратно переворачиваем
}

///////////////////////////////////////////////////////////////////////// Покупка товара

function get_product_list() /// Список товара
{
    global $db, $table;

    static $product = NULL;
    if ($product === NULL) {
        $data = read_cache('area_product_list');
        if ($data === false) {
            $sql = "select * from {$table}product order by product asC"; // Основная категория
            $res_1 = $db->getAll($sql);
            $product = array();
            foreach ($res_1 as $row_1) {
                $product[$row_1['product']] = $row_1;
            }
            write_cache('area_product_list', $product);
        } else {
            $product = $data;
        }
    }
    return $product;
}



//////////////////// Переделаные регионы
function get_area_list()
{
    global $db, $table;

    static $areas = NULL;
    if ($areas === NULL) {
        $data = read_cache('area_list');
        if ($data === false) {
            $sql = "select * from {$table}area_1 where is_show = '1'  order by sortid asC"; // Основная категория
            $res_1 = $db->getAll($sql);
            $areas = array();
            foreach ($res_1 as $row_1) {
                $areas[$row_1['areaid']] = $row_1;
                $parent_row = $row_1['areaid'];
                $sql = "select * from {$table}area_2 where is_show = '1' and  parentid = '$parent_row' order by sortid asC"; // Основная категория
                $res_2 = $db->getAll($sql);
                if ( !empty($res_2)) {
                    foreach ($res_2 as $row_2) {
                        $areas[$row_1['areaid']]['children'][$row_2['areaid']] = $row_2;
                    }
                }
            }
            write_cache('area_list', $areas);
        } else {
            $areas = $data;
        }
    }
    return $areas;
}


function get_area_child_list()
{
    global $db, $table;

    static $areas = NULL;
    if ($areas === NULL) {
        $data = read_cache('area_child_list');
        if ($data === false) {
            $sql = "select * from {$table}area_2 where is_show = '1'  order by sortid asC"; // Основная категория
            $res_1 = $db->getAll($sql);
            $areas = array();
            foreach ($res_1 as $row_1) {
                $areas[$row_1['areaid']] = $row_1;
            }
            write_cache('area_child_list', $areas);
        } else {
            $areas = $data;
        }
    }
    return $areas;
}

function get_area_children($areaid) // Список регионов в которых поиск через запятую
{
    $areas = get_area_list();
    $area_children = $areas[$areaid]['children'];
    if (is_array($area_children)) { // это регион в данный момент город или регион только для реального реиона
        $id = '';
        foreach ($area_children as $child) {
            $id .= $child['areaid'] . ',';
        }
        $result = substr($id, 0, -1);

    } else { // если выбран город

        $areas = get_area_child_list();
        $result = $areas[$areaid]['searchid'];
    }

    return $result;
}

function area_options($selected = '', $default_text= '' ) // Загружаем список регионов
{
    global $lang;
    $area = get_area_list();
    $option = "<option value=''>" . $default_text . "</option>";
    foreach ($area as $area) {
        if (empty($area['children'])) {
            $option .= "<option value=$area[areaid]";
            $option .= ($selected == $area['areaid']) ? " selected='selected'" : '';
            $option .= ">". tr($area['areaname']). "</option>";
        } else {
            foreach ($area['children'] as $chi) {
                $option .= "<option value=$chi[areaid]";
                $option .= ($selected == $chi['areaid']) ? " selected='selected'" : '';
                $option .= ">". tr($area['areaname']). " ". tr($chi['areaname']) ." </option>";
            }
        }
    }
    return $option;
}

//////////////////// Переделаные категории

function get_cat_list() // Список всех категорий с массивом под категорий в children
{
    global $db, $table;
    static $cats = NULL;
    if ($cats === NULL) {
        $data = read_cache('cat_list');
        if ($data === false) {
            $sql = "select * from {$table}category_1 where is_show = '1' order by sortid asC";
            $res_1 = $db->getAll($sql);
            $cats = array();
            foreach ($res_1 as $row_1) {
                $cats[$row_1['catid']] = $row_1;

                $parent_row = $row_1['catid'];
                $sql = "select * from {$table}category_2 where is_show = '1' and  parentid = '$parent_row' order by sortid asC"; // Основная категория
                $res_2 = $db->getAll($sql);
                if ( !empty($res_2)) {
                    foreach ($res_2 as $row_2) {
                        $cats[$row_1['catid']]['children'][$row_2['catid']] = $row_2;
                    }
                }
            }
            write_cache('cat_list', $cats);
        } else {
            $cats = $data;
        }
    }
    return $cats;
}

function get_cat_child_list() //  Список всех под категорий по catid на будущее
{
    global $db, $table;
    static $cats = NULL;
    if ($cats === NULL) {
        $data = read_cache('cat_child_list');
        if ($data === false) {
            $sql = "select * from {$table}category_2 where is_show = '1' order by catid asC";
            $res_1 = $db->getAll($sql);
            $cats = array();
            foreach ($res_1 as $row_1) {
                $cats[$row_1['catid']] = $row_1;
            }
            write_cache('cat_child_list', $cats);
        } else {
            $cats = $data;
        }
    }
    return $cats;
}

function get_cat_info ($catid){
    $cat = get_cat_child_list();
    return $cat[$catid];
}

//function cat_options($selectid = '', $catid = '') // Кажется нет надобности
//{
//    $option = '';
//
//    $cats = get_cat_list();
//    if ($catid) {
//        $cats = $cats[$catid];
//    }
//    foreach ((array)$cats as $cat) {
//        $option .= "<option value=$cat[catid] style='color:red;'";
//        $option .= ($selectid == $cat['catid']) ? " selected='selected'" : '';
//        $option .= ">$cat[catname]</option>";
//
//        if (!empty($cat['children'])) {
//            foreach ($cat['children'] as $chi) {
//                $option .= "<option value=$chi[id]";
//                $option .= ($selectid == $chi['id']) ? " selected='selected'" : '';
//                $option .= ">&nbsp;&nbsp;|--$chi[name]</option>";
//            }
//        }
//    }
//    return $option;
//}

function get_cat_children_html( $catid ,$selectid = '', $default_text= '') // Список html всех под категорий
{
    $cats = get_cat_list();
    $cats_child = $cats[$catid]['children'];
    $option =  "<option value='0'>" . $default_text . "</option>";

    foreach ((array)$cats_child as $cat) {
        $option .= "<option value=$cat[catid]";
        $option .= ($selectid == $cat['catid']) ? " selected='selected'" : '';
        $option .= ">".tr($cat['catname'])."</option>";
    }
    return $option;
}

function get_cat_children( $catid ) //////// Отдаем список дочерних каталогов через запятую
{

    $cats = get_cat_list();
    $cats_child = $cats[$catid]['children'];
    $result ='';
    foreach ((array)$cats_child as $cat) {
        $result.=  $cat['catid'] . ',';
    }
    $result = substr($result, 0, -1); // Удаляем последнюю запятую
    return $result;

}

////////////////////////////// index.php

function get_count_category() // Чичсло записей в категории, индексируем по catid
{
    global $db, $table;
    $sql = "select catid, count(catid) as count_cat from {$table}info where is_check=1 AND is_hide=0  group by catid ";
    $res = $db->getAll($sql);

    $cats = array();
    foreach ($res as $row) {
        $cats[$row['catid']] = $row['count_cat'];
    }
    return $cats;
};

function get_count_category_all() // Чичсло записей в категории, индексируем по catid
{
    global $db, $table;
    $sql = "select count(catid) as count_all from {$table}info where is_check=1 AND is_hide=0";
    $res = $db->getOne($sql);

    return $res;
};

function get_count_category_new() // Чичсло записей в категории, индексируем по catid
{
    global $db, $table;

    $time_dey = time() - 86400;

    $sql = "select count(catid) as count_new from {$table}info where is_check=1 AND is_hide=0  AND is_postdate > $time_dey ";
    $res = $db->getOne($sql);

    return $res;
};
////////////////////////////// Работа с чатом

function create_db($id_user){
    $db_file = 'user/U0000002/db/mysqlitedb.db';
    try {
        $db = new SQLite3 ($db_file);
        $db->exec("INSERT INTO foo (bar) VALUES ('This is a test')") ;
        $result = $db->query('SELECT bar FROM foo');
        print_r($result->fetchArray('bar'));
//        if   ($db->exec('CREATE TABLE foo (bar STRING)') ){
//            $result = $db->query('SELECT bar FROM foo');
//        }else{
//            $result = 'error';
//        }

        $db->close();
    }   catch (Exception $ex) {
        $result = $ex ;
    }


//    $db->exec('CREATE TABLE foo (bar STRING)');
//    $db->exec('DROP TABLE foo');
//    if ( $db->exec("INSERT INTO foo (bar) VALUES ('This is a test 2')") ){
//        $result = $db->query('SELECT bar FROM foo');
//    } else {
//        $result = 'Error';
//    }

    print_r($result);



//    if ($db = sqlite_open('user/U0000002/db/mysqlitedb.db', 0666, $sqliteerror)) {
//        sqlite_query($db, 'CREATE TABLE foo (bar varchar(10))');
//        sqlite_query($db, "INSERT INTO foo VALUES ('fnord')");
//        $result = sqlite_query($db, 'select bar from foo');
//        var_dump(sqlite_fetch_array($result));
//    } else {
//        echo($sqliteerror);
//    }

//    $dbconn = sqlite3_open('user/U0000002/db/mysqlitedb.db');
//
//    if ($dbconn) {
//        sqlite_query($dbconn, "CREATE TABLE dogbreeds (Name VARCHAR(255), MaxAge INT);");
//        sqlite_query($dbconn, "INSERT INTO dogbreeds VALUES ('Doberman', 15)");
//        $result = sqlite_query($dbconn, "SELECT Name FROM dogbreeds");
//        var_dump(sqlite_fetch_array($result, SQLITE_ASSOC));
//    } else {
//        print "Connection to database failed!\n";
//    }


}