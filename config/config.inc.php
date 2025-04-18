﻿<?php

// Файл конфигурации для Flit CMS
// Эти настройки можно менять из админ панели
// или редактировать вручную в этом файле  

return [
    // Общий заголовок для всех страниц
    'siteName' => 'Flit CMS',
    'version' => '1.16', // Версия CMS
    'adminEmail' => '399221@gmail.com',
    // Список допустимых языков
    'validLanguages' => 'ru, en, de, ka',
     // Основной язык
    'defaultLanguage' => 'ru',
    // Имя куки для языка
    'langCookieName' => 'LANG',   
    // Имя файла в папке с языковыми файлами, который содержит слова, для которых не найден перевод
    'missingTranslationsFileName' => 'MissingTranslations.txt',
     // Путь к языковым файлам и к файлу для записи отсутствующих ключей
    'langDir' => 'config/lang', 
    // Папка с шаблонами, используемая для текущей версии сайта
    'tplDir' => 'demo', // Папка с шаблонами (например: 'default', 'v1', 'v2')
    // Папка с шаблонами для админ панели
    'tplAdminDir' => 'admin', // Папка с шаблонами для админки
    // Папка для кеширования скомпилированных шаблонов
    'tplCacheDir' => 'cache', // Папка для кеширования скомпилированных шаблонов        
    // Время жизни сессии
    // Если передано '0', сессия будет жить до закрытия браузера
    // Если передано пустое значение '-1' - сессия будет бесконечной (до очистки кеша)
    // Если передано число, это будет время жизни сессии в часах
    'lifeTime'  => '-1',
    'sessionKey' => 'user_data', // Ключ для хранения данных пользователя в сессии
    'userCookeName' => 'LIVE_ID', // Имя куки для языка пользвателя
    // Включение сжатия шаблонов для экономии места и ускорения загрузки (true/false)
    'tplCompress' => true,
    // Принудительная компиляция шаблонов при каждом запросе (true/false)
    'tplForceCompile' => true,
    // Тип хранилища данных: 'json', 'mysql'
    // В зависимости от этого параметра выбирается, где и как будут храниться данные
    'storage' => 'json', // Выбор хранилища данных: 'json', 'mysql'
    // Настройки для MySQL
    // Используются для подключения к базе данных MySQL
    'mysql' => [
        'host' => 'localhost', // Адрес хоста MySQL (например, 'localhost' или IP-адрес сервера)
        'dbname' => 'f_micro_cms', // Имя базы данных, которая используется для CMS
        'user' => 'root', // Имя пользователя для подключения к базе данных
        'password' => '1234', // Пароль базы
        'charset' => 'utf8mb4', // Кодировка для базы данных (UTF-8 с поддержкой символов Emoji)
        // Настройки для регистрации пользователей
        'UsersTableName' => 'user', // Название таблицы в MySQL, где хранятся данные о пользователях
    ],
    // Настройки для хранения данных в формате JSON
    // Используются, если выбрано хранилище типа 'json'
    'json' => [
        'path' => 'data', // Путь, где хранятся все данные (директория)data/data
        'UsersTableName' => 'user', // Название json файла, где хранятся данные о пользователях /data/data/user.json
    ],

];

?>

