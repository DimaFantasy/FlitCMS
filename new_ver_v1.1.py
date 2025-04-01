"""
Модуль для обновления версий файлов и замены их содержимого.

Основные функции:
- Автоматическое обновление версий файлов (например, logAndStateManagement_v1.03.js -> logAndStateManagement_v1.04.js).
- Поиск и замена всех вхождений старого имени файла на новое в указанных файлах.
- Логирование всех операций с цветовым выделением и фильтрацией по уровням.

"""

import os
import re
from tqdm import tqdm # Без него коменты без цвета
import glob

# Глобальные константы
FILE_NAME = r' assets\js\main_v0.1.js, assets\css\styles_0.1.css ' # Файлы для обновления версии через запятую. Указать точный путь к файлу. 
# Формат: <базовое_имя>X.Y.<расширение>, где X=1-5 цифр (версия), Y=1-2 цифры (подверсия)
# Пример корректных имён: "main_v123.45.js", "data_V5.1.TXT", "APP_00001.02.js"
# Регистр букв в имени сохраняется при обновлении версии, поиск регистронезависимый 
WHITELIST_PATHS = r'./'  # Папки для поиска файлов, поиск регистронезависимый  
BLACKLIST_PATHS = r' .git, .vscode'  # Папки и файлы, которые исключая из поиска, регистронезависимы 
# ../, .../, ..../ — могут быть только в начале пути, 
# означают переход вверх на 1+ директорий, './' корень, за точками должен следовать слеш.  
# Поддерживаются / и \, разницы нет.  
# / или \ в начале — корень диска, иначе — корень скрипта.  
# Также допускаются абсолютные пути с указанием диска.
BLACKLIST_EXTEN = r'.exe, .dll, .py, .rar'  # Запрещенные расширения. Без учета регистра.

# Настройки системы логирования
LOG_SETTINGS = {
    'ENABLED': True,                # Глобальное включение/выключение логов
    'LEVELS': {
        'DEBUG': False,              # Детальная отладочная информация
        'INFO': True,                # Основные информационные сообщения
        'WARNING': True,             # Предупреждения
        'ERROR': True,               # Критические ошибки
        'SUCCESS': True,             # Успешные операции
        'SKIPPED': True,             # Пропущенные элементы
        'CREATED': True              # Созданные объекты
    }
}

def log(msg_type, message):
    """
    Система логирования с цветовым выделением и фильтрацией сообщений.
    
    Параметры:
        msg_type (str): Тип сообщения (DEBUG, INFO, WARNING и т.д.)
        message (str): Текст сообщения для вывода
    """
    if not LOG_SETTINGS['ENABLED']:
        return
        
    levels = LOG_SETTINGS['LEVELS']
    color_codes = {
        'DEBUG': '\033[94m',     # Синий
        'INFO': '\033[0m',       # Стандартный
        'WARNING': '\033[93m',   # Желтый
        'ERROR': '\033[91m',     # Красный
        'SUCCESS': '\033[92m',   # Зеленый
        'SKIPPED': '\033[90m',   # Серый
        'CREATED': '\033[96m'    # Голубой
    }
    
    reset_code = '\033[0m'
    
    if levels.get(msg_type, False):
        color = color_codes.get(msg_type, '')
        print(f"{color}[{msg_type}]{reset_code} {message}")

def get_files_with_version(directory, base_name, base_extension):
    """
    Возвращает список файлов в указанной директории, которые соответствуют шаблону имени с версией.
    Поиск осуществляется без учета регистра, но возвращаются оригинальные имена файлов.

    :param directory: Директория для поиска файлов.
    :param base_name: Базовое имя файла без версии.
    :param base_extension: Расширение файла.
    :return: Список файлов, соответствующих шаблону (в оригинальном регистре).
    """
    pattern = re.compile(r"^" + re.escape(base_name) + r"\d{1,5}\.\d{1,2}" + re.escape(base_extension) + r"$", re.IGNORECASE)
    files = []
    
    for file in os.listdir(directory):
        if pattern.match(file):
            files.append(file)  # Сохраняем оригинальное имя файла
    
    return files


def update_filename_version(directory, file_name):
    """
    Обновляет версию файла, увеличивая минорную версию на 1. Если минорная версия достигает 99,
    она сбрасывается до 0, а мажорная версия увеличивается на 1. Сохраняет оригинальный регистр имени файла.

    :param directory: Директория, в которой находится файл.
    :param file_name: Имя файла с версией.
    :return: Кортеж (старое имя файла, новое имя файла) или None, если произошла ошибка.
    """
    # Разделяем имя файла на базовое имя и расширение
    base_name, base_extension = os.path.splitext(file_name)   
    # Удаляем версию из имени файла, используя регулярное выражение
    base_name_without_version = re.sub(r'\d{1,5}\.\d{1,2}$', '', base_name, flags=re.IGNORECASE)  
    # Получаем список файлов, соответствующих шаблону имени с версией (без учёта регистра)
    files = get_files_with_version(directory, base_name_without_version, base_extension)    
    # Если найдено несколько файлов, выводим ошибку и завершаем выполнение
    if len(files) > 1:
        log('ERROR', f"Найдено несколько файлов. Пожалуйста, скорректируйте имя файла.")
        return None    
    # Если файлов не найдено, выводим ошибку и завершаем выполнение
    if not files:
        log('ERROR', f"Не найден файл для обновления версии.")
        return None        
    # Берем первый (и единственный) файл из списка
    original_file_name = files[0]    
    # Ищем версию в имени файла с помощью регулярного выражения (без учёта регистра)
    pattern = r"(\d{1,5})\.(\d{1,2})" + re.escape(base_extension) + r"$"
    match = re.search(pattern, original_file_name, flags=re.IGNORECASE)   
    # Если версия найдена
    if match:
        # Извлекаем мажорную и минорную версии
        major_version = int(match.group(1))
        minor_version = int(match.group(2))        
        # Увеличиваем минорную версию на 1
        if minor_version < 99:
            minor_version += 1
        else:
            # Если минорная версия достигает 99, сбрасываем её до 0 и увеличиваем мажорную версию
            minor_version = 0
            major_version += 1       
        # Формируем новую версию в формате "мажорная.минорная"
        new_version = f"{major_version}.{minor_version:02d}"        
        # Сохраняем оригинальное имя файла (с оригинальным регистром), но обновляем версию
        new_file_name = re.sub(
            pattern,
            f"{new_version}{base_extension}",
            original_file_name,
            flags=re.IGNORECASE
        )        
        # Формируем полные пути к старому и новому файлу
        old_file_path = os.path.join(directory, original_file_name)
        new_file_path = os.path.join(directory, new_file_name)        
        # Проверяем, существует ли старый файл
        if not os.path.exists(old_file_path):
            log('ERROR', f"Файл '{old_file_path}' не найден.")
            return None        
        try:
            # Переименовываем файл
            os.rename(old_file_path, new_file_path)
            log('CREATED', f"Файл переименован с {original_file_name} на {new_file_name}")
            # Возвращаем кортеж с именами старого и нового файла
            return (original_file_name, new_file_name)
        except Exception as e:
            # В случае ошибки при переименовании выводим сообщение об ошибке
            log('ERROR', f"Ошибка при переименовании файла: {e}")
            return None           
    else:
        # Если версия в имени файла не найдена, выводим сообщение и завершаем выполнение
        log('SKIPPED', f"В имени файла {original_file_name} не найдена версия.")
        return None

def create_regex(old_name):
    """Создает регулярное выражение для поиска всех версий файла на основе old_name."""
    base, ext = os.path.splitext(old_name)
    version_pattern = r'\d{1,5}\.\d{1,2}'
    matches = list(re.finditer(version_pattern, base))    
    if not matches:
        return None  # Если версия не найдена    
    last_match = matches[-1]
    start, end = last_match.start(), last_match.end()
    prefix = base[:start]
    suffix = base[end:]    
    # Экранируем специальные символы
    regex_prefix = re.escape(prefix)
    regex_suffix = re.escape(suffix)
    regex_ext = re.escape(ext)    
    # Собираем регулярное выражение
    regex = f"{regex_prefix}\\d{{1,5}}\\.\\d{{1,2}}{regex_suffix}{regex_ext}"
    return regex

def update_file(file_path, regex_pattern, new_name):
    """Обновляет все вхождения по регулярному выражению в файле на новое имя.
    
    :param file_path: Путь к файлу для обновления
    :param regex_pattern: Скомпилированное регулярное выражение для поиска
    :param new_name: Новое имя для замены
    :return: True, если файл был обновлен, иначе False
    """
    try:
        if not os.path.exists(file_path):
            log('ERROR', f"Файл '{file_path}' не существует")
            return False
        # Чтение содержимого файла
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()
        except UnicodeDecodeError:
            log('SKIPPED', f"Файл {file_path} - бинарный или не UTF-8")
            return False
        except Exception as e:
            log('ERROR', f"Ошибка чтения {file_path}: {str(e)}")
            return False
        # Замена содержимого
        try:
            # Компилируем регулярное выражение с флагом IGNORECASE
            compiled_regex = re.compile(regex_pattern, re.IGNORECASE)
        except re.error as e:
            log('ERROR', f"Ошибка в регулярном выражении '{regex_pattern}': {e}")
            return False
        # Выполняем замену
        new_content, replacements = compiled_regex.subn(new_name, content)
        if replacements == 0:
            log('SKIPPED', f"В файле {file_path} не найдено вхождений для замены")
            return False
        # Запись изменений
        try:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(new_content)
            log('SUCCESS', f"В файле {file_path} заменено {replacements} вхождений")
            return True
        except Exception as e:
            log('ERROR', f"Ошибка записи в {file_path}: {str(e)}")
            # Восстановление оригинального содержимого
            try:
                with open(file_path, 'w', encoding='utf-8') as f:
                    f.write(content)
                log('INFO', f"Восстановлен оригинальный файл {file_path}")
            except Exception as e:
                log('CRITICAL', f"Файл {file_path} поврежден! Невозможно восстановить!")
            return False            
    except Exception as e:
        log('ERROR', f"Ошибка обработки файла '{file_path}': {str(e)}")
        return False

def update_file2(file_path, old_name, new_name):
    """Обновляет все вхождения старого имени в указанном файле на новое.

    :param file_path: Путь к файлу для обновления
    :param old_name: Старое имя для замены
    :param new_name: Новое имя    
    """
    try:
        # Проверяем, существует ли файл
        if not os.path.exists(file_path):
            log('ERROR', f"Файл '{file_path}' не существует")
            return
        # Чтение файла
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()
        except UnicodeDecodeError:
            log('SKIPPED', f"Файл {file_path} - бинарный или не UTF-8")
            return
        except Exception as e:
            log('ERROR', f"Ошибка чтения {file_path}: {str(e)}")
            return
        # Замена содержимого
        replacements = content.count(old_name)
        if not replacements:
            log('SKIPPED', f"В файле {file_path} нет вхождений")
            return
        new_content = content.replace(old_name, new_name)
        # Запись изменений
        try:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(new_content)
            log('SUCCESS', f"Обновлено {replacements} вхождений в {file_path}")
        except Exception as e:
            log('ERROR', f"Ошибка записи в {file_path}: {str(e)}")
            # Пытаемся восстановить оригинальное содержимое
            try:
                with open(file_path, 'w', encoding='utf-8') as f:
                    f.write(content)
                log('INFO', f"Восстановлен оригинальный файл {file_path}")
            except:
                log('CRITICAL', f"Файл {file_path} поврежден! Невозможно восстановить!")
    except Exception as e:
        log('ERROR', f"Ошибка обработки файла '{file_path}': {str(e)}")
        


def resolve_path(path, base_dir=None):
    """
    Преобразует относительный путь с поддержкой многоточной нотации в абсолютный.
    
    Примеры:
        "..../file.txt" -> "../../../file.txt"
        "../test" -> родительская директория + test        
    Параметры:
        path (str): Относительный путь для преобразования
        base_dir (str): Базовая директория (по умолчанию текущая)       
    Возвращает:
        str: Абсолютный нормализованный путь
    """
    if base_dir is None:
        base_dir = os.getcwd()   
    if not path or path.isspace():
        return base_dir       
    path = path.strip().replace('\\', '/')    
    if os.path.isabs(path):
        return path       
    if path.startswith('.'):
        parts = path.split('/')
        first_part = parts[0]       
        if all(char == '.' for char in first_part):
            current = base_dir
            for _ in range(len(first_part) - 1):
                parent = os.path.dirname(current)
                if parent == current:
                    break
                current = parent               
            return os.path.join(current, *parts[1:]) if len(parts) > 1 else current           
    return os.path.join(base_dir, path)
    
import os

def normalize_items(items, script_dir, local_dir):
    """
    Нормализует пути из строки, разделенной запятыми.
    Нормализует пути, обрабатывая многоточечную нотацию ('..', '...') и преобразуя их в относительные.
    Если путь начинается с другого диска, используется абсолютный путь.
    Поддерживает оба вида слешей  в путях.
    Возвращает все в нижнем регистре
    
    Args:
        items (str): Строка с путями, разделенными запятыми
        script_dir (str): Директория скрипта
        local_dir (str): Локальная директория
    """
    if not items or items.isspace():
        return []  
    result = []
    for item in items.split(","):
        item = item.strip()
        if not item:
            continue           
        try:
            # Проверяем наличие буквы диска в начале пути
            drive_match = re.match(r'^([A-Za-z]:).*', item)
            if drive_match:
                # Путь уже содержит букву диска - оставляем как есть
                abs_path = os.path.normpath(item)
                log('DEBUG', f"Путь с буквой диска: {item} -> {abs_path}")
                if os.path.exists(abs_path):
                    result.append(abs_path)
                else:
                    log('ERROR', f"Путь не существует: {abs_path}")
                continue
            # Проверяем начинается ли путь с корня диска
            if item.startswith('/') or item.startswith('\\'):
                current_drive = os.path.splitdrive(local_dir)[0]
                abs_path = os.path.normpath(current_drive + item.replace('\\', '/'))
                log('DEBUG', f"Абсолютный путь от корня: {item} -> {abs_path}")
                if os.path.exists(abs_path):
                    result.append(abs_path)
                else:
                    log('ERROR', f"Путь не существует: {abs_path}")
                continue
            # Обработка специального синтаксиса с точками
            if item.startswith('...'):
                dots_count = len(re.match(r'\.+', item).group())
                levels_up = dots_count - 1
                remaining_path = item[dots_count:]
                
                base_path = script_dir
                for _ in range(levels_up):
                    base_path = os.path.dirname(base_path)               
                if remaining_path:
                    # Убираем начальный слеш любого вида
                    if remaining_path.startswith('/') or remaining_path.startswith('\\'):
                        remaining_path = remaining_path[1:]
                    abs_path = os.path.normpath(os.path.join(base_path, remaining_path))
                else:
                    abs_path = os.path.normpath(base_path)                    
                log('DEBUG', f"Путь с точками: {item} -> {abs_path}")               
                # Проверяем, находятся ли пути на одном диске
                if os.path.splitdrive(abs_path)[0].lower() == os.path.splitdrive(local_dir)[0].lower():
                    # Вычисляем относительный путь от local_dir
                    rel_path = os.path.relpath(abs_path, local_dir)
                    result.append(rel_path)
                else:
                    # Если пути на разных дисках, используем абсолютный путь
                    result.append(abs_path)
                continue
            # Обычный относительный путь (../temp и подобные)
            abs_path = os.path.normpath(os.path.join(script_dir, item))           
            # Проверяем, находятся ли пути на одном диске
            if os.path.splitdrive(abs_path)[0].lower() == os.path.splitdrive(local_dir)[0].lower():
                # Вычисляем относительный путь от local_dir
                rel_path = os.path.relpath(abs_path, local_dir)
                result.append(rel_path)
            else:
                # Если пути на разных дисках, используем абсолютный путь
                result.append(abs_path)           
            log('DEBUG', f"Обработанный путь: {item} -> {result[-1]}")
        except ValueError as e:
            log('WARNING', f"Ошибка обработки пути {item}: {e}")            
    # Приводим все элементы в нижний регистр перед возвратом
    return [path.lower() for path in result]

# Функция для обработки списка путей
def process_path_list(paths, script_dir, local_dir):
    """
    Обрабатывает список путей, добавляя local_dir, если путь не содержит диска.
    """
    normalized = normalize_items(paths, script_dir, local_dir)
    for i in range(len(normalized)):
        drive, path = os.path.splitdrive(normalized[i])
        if not drive:
            normalized[i] = os.path.join(local_dir, normalized[i]).replace('\\', '/')
        else:
            normalized[i] = normalized[i].replace('\\', '/')
            
    return [path.lower() for path in normalized]
 
def traverse_files(white_list, black_list, black_ext):
    """
    Обходит все файлы и директории в белом списке, исключая черный список и расширения.
    """
    all_files = []
    for item in white_list:
        if os.path.isdir(item):
            for root, dirs, files in os.walk(item):
                for file in files:
                    full_path = os.path.join(root, file).replace('\\', '/')
                    if is_excluded(full_path, black_list, black_ext):
                        continue
                    all_files.append(full_path)
        elif os.path.isfile(item):
            if not is_excluded(item, black_list, black_ext):
                all_files.append(item)

    return all_files

def is_excluded(path, black_list, black_ext):
    """
    Проверяет, исключен ли путь из обработки.
    """
    for excluded_item in black_list:
        if path.lower().startswith(excluded_item.lower()):
            return True
    ext = os.path.splitext(path)[1].lower()
    if ext in black_ext:
        return True
    return False 
       
def main():

    # Получаем корень скрипта
    script_dir = os.path.abspath(os.getcwd())
    # Получаем имя диска где находится скрипт
    drive, _ = os.path.splitdrive(os.getcwd())
    root_dir = os.path.join(drive, os.sep) if drive else os.sep
    # Обработка белого списка
    white_list = process_path_list(WHITELIST_PATHS, script_dir, root_dir)
    log('DEBUG', f"Белый список: {white_list}")
    # Обработка черного списка
    black_list = process_path_list(BLACKLIST_PATHS, script_dir, root_dir)
    log('DEBUG', f"Черный список: {black_list}")
    # Обработка расширений черного списка
    black_ext = [ext.strip().lower() for ext in BLACKLIST_EXTEN.split(",")]
    log('DEBUG', f"Расширения черного списка: {black_ext}")    
    # Поиск файлов
    all_files = traverse_files(white_list, black_list, black_ext)    
    # Если файлы найдены - работаем с ними
    if all_files:
        log('SUCCESS', f"Всего найдено файлов: {len(all_files)}")       
        # Разбиваем JS_FILE_NAME на список имен файлов и удаляем пробелы
        js_file_names = process_path_list(FILE_NAME, script_dir, root_dir)
        for js_file_name in js_file_names:
            log('INFO', f"Начинаем смену версии для файла: {js_file_name}")            
            # Получаем абсолютный путь к файлу с учетом базовой директории скрипта
            abs_file_path = os.path.normpath(os.path.join(script_dir, js_file_name))
            # Разбиваем путь на компоненты
            full_path = os.path.dirname(abs_file_path)
            file_name = os.path.basename(abs_file_path)
            # Проверяем существование директории
            if not os.path.isdir(full_path):
                log('ERROR', f"Директория не существует: {full_path}")
                continue  # Пропускаем обработку этого файла          
            log('DEBUG', f"Обрабатываем файл: {file_name} в директории: {full_path}")           
            result = update_filename_version(full_path, file_name)
            if not result:
                log('ERROR', f"Не удалось обновить версию файла: {js_file_name}")
                continue  
            old_name, new_name = result
            regex = create_regex(old_name)
            if not regex:
                log('ERROR', f"Не удалось создать шаблон для '{old_name}'. Прекращаем обработку.")
                continue  
            log('DEBUG', f"Регулярное выражение для поиска замены: {regex}")            
            # Обработка файлов
            for file_path in all_files:
                update_file(file_path, regex, new_name)
                #if update_file(file_path, regex, new_name):
                #    log('INFO', f"Успешно обработан: {file_path}")
                #else:
                #    log('INFO', f"Не удалось обработать: {file_path}")
    # Если файлов нет - выводим ошибку
    else:
        log('ERROR', "Файлы для обработки не найдены. Проверьте белый и черный списки.")
    # Запрос Нажмите Enter для выхода...
    input("Нажмите Enter для выхода...")

if __name__ == '__main__':
    main()