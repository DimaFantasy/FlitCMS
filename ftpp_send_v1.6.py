"""
Модуль для загрузки файлов на FTP-сервер с поддержкой исключений, прогресс-бара и обработкой ошибок.
"""

import ftplib
import os
import getpass
import time
from tqdm import tqdm
import socket

# Конфигурационные параметры
FTP_HOST = r""  # Адрес FTP-сервера
FTP_USER = r""  # Логин пользователя FTP
FTP_PASS = r""  # Пароль пользователя FTP
REMOTE_DIR = r"/"  # Целевая директория на сервере
LOCAL_DIR = r"."  # Локальная директория для загрузки

# Настройки исключений
EXCLUDED_EXTENSIONS = r" .lnk, .py , .rar"  # Расширения файлов для исключения
EXCLUDED_FILES_DIRS =  r" .git,.vscode.,cache, includes\temp "  # Исключаемые файлы и директории

ENCODING = r"cp1251"  # Кодировка ftp сервера для соединения

MAX_RETRIES = 3  # Максимальное количество попыток подключения и загрузки
RETRY_DELAY = 5  # Интервал между попытками в секундах

MIN_FILESIZE_FOR_PROGRESSBAR = 102400  # 100 КБ Выще этого размера грузим прогресбаром

# Настройки системы логирования
LOG_SETTINGS = {
    "ENABLED": True,  # Глобальное включение/выключение логов
    "LEVELS": {
        "DEBUG": False,  # Детальная отладочная информация
        "INFO": False,  # Основные информационные сообщения
        "WARNING": False,  # Предупреждения
        "ERROR": False,  # Критические ошибки
        "SUCCESS": False,  # Успешные операции
        "SKIPPED": False,  # Пропущенные элементы
        "CREATED": False,  # Созданные объекты
    },
}


def log(msg_type, message):
    """
    Система логирования с цветовым выделением и фильтрацией сообщений.

    Параметры:
        msg_type (str): Тип сообщения (DEBUG, INFO, WARNING и т.д.)
        message (str): Текст сообщения для вывода
    """
    if not LOG_SETTINGS["ENABLED"]:
        return

    levels = LOG_SETTINGS["LEVELS"]
    color_codes = {
        "DEBUG": "\033[94m",  # Синий
        "INFO": "\033[0m",  # Стандартный
        "WARNING": "\033[93m",  # Желтый
        "ERROR": "\033[91m",  # Красный
        "SUCCESS": "\033[92m",  # Зеленый
        "SKIPPED": "\033[90m",  # Серый
        "CREATED": "\033[96m",  # Голубой
    }

    reset_code = "\033[0m"

    if levels.get(msg_type, False):
        color = color_codes.get(msg_type, "")
        print(f"{color}[{msg_type}]{reset_code} {message}")


def is_server_available(host, port=21, timeout=5):
    """
    Проверяет доступность FTP-сервера через сокет.

    Параметры:
        host (str): Хост или IP-адрес сервера
        port (int): Порт для подключения (по умолчанию 21)
        timeout (int): Таймаут подключения в секундах
    Возвращает:
        bool: True если сервер доступен, False в противном случае
    """
    try:
        socket.create_connection((host, port), timeout=timeout)
        return True
    except (socket.timeout, ConnectionRefusedError):
        return False


def is_excluded_dir(dir_path, excluded_dirs):
    """
    Проверяет, находится ли директория в списке исключений или является дочерней для исключенной.
    Параметры:
        dir_path (str): Проверяемый путь (относительно LOCAL_DIR)
        excluded_dirs (list): Список исключенных директорий
    Возвращает:
        bool: True если директория должна быть исключена
    """
    # Приводим пути к нижнему регистру и заменяем обратные слеши на прямые для единообразия
    dir_path = dir_path.replace("\\", "/").lower()
    # Перебираем список исключенных директорий
    for excluded_dir in excluded_dirs:
        # Приводим исключенную директорию к нижнему регистру и заменяем обратные слеши на прямые
        excluded_dir = excluded_dir.replace("\\", "/").lower()
        # Проверяем, начинается ли dir_path с excluded_dir + '/',
        # что означает, что dir_path является поддиректорией excluded_dir,
        # или если dir_path совпадает с excluded_dir.
        if dir_path.startswith(excluded_dir + "/") or dir_path == excluded_dir:
            return True  # Если условие выполняется, значит, директория исключается
    return False  # Если ни одно условие не выполнилось, директория не исключается


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
    # Если базовая директория не указана, используем текущую рабочую директорию
    if base_dir is None:
        base_dir = os.getcwd()
    # Если путь пустой или состоит только из пробелов, возвращаем базовую директорию
    if not path or path.isspace():
        return base_dir
    # Удаляем лишние пробелы в начале и конце пути, заменяем обратные слеши на прямые
    path = path.strip().replace("\\", "/")
    # Если путь уже абсолютный, возвращаем его без изменений
    if os.path.isabs(path):
        return path
    # Обработка многоточной нотации
    if path.startswith("."):
        parts = path.split("/")
        first_part = parts[0]
        # Проверяем, состоит ли первая часть пути только из точек
        if all(char == "." for char in first_part):
            current = base_dir
            # Подъем на N-1 уровней для N точек
            for _ in range(len(first_part) - 1):
                parent = os.path.dirname(current)
                if parent == current:  # Достигли корневой директории
                    break
                current = parent
            # Соединяем оставшиеся части пути с текущей директорией
            return os.path.join(current, *parts[1:]) if len(parts) > 1 else current
    # Если путь не начинается с точек, присоединяем его к базовой директории
    return os.path.join(base_dir, path)


def normalize_path(path):
    """
    Нормализует путь для кроссплатформенной совместимости.

    Заменяет обратные слеши на прямые и удаляет дублирующиеся разделители.

    Параметры:
        path (str): Исходный путь
    Возвращает:
        str: Унифицированный путь в UNIX-стиле
    """
    return os.path.normpath(path).replace("\\", "/").replace("//", "/")


def ensure_remote_dir(ftp, remote_path):
    """
    Рекурсивно создает директории на FTP-сервере при необходимости.

    Args:
        ftp (ftplib.FTP): Объект FTP-соединения.
        remote_path (str): Целевой путь на сервере.
    Returns:
        bool: True, если все директории успешно созданы, иначе False.
    """

    # Проверяем, не является ли путь пустым или корнем.
    if remote_path in ("/", ".", ""):
        return True
    # Нормализуем путь, удаляем ведущий слеш и разделяем на компоненты.
    parts = normalize_path(remote_path).lstrip("/").split("/")
    current = ""
    for part in parts:
        if not part:
            continue
        # Формируем текущий путь.
        current = f"{current}/{part}" if current else part
        try:
            # Пытаемся перейти в текущую директорию.
            ftp.cwd(
                "/"
            )  # Сначала переходим в корень, чтобы избежать проблем с относительными путями.
            ftp.cwd(current)
            log(
                "DEBUG", f"Перешли в директорию: {current}"
            )  # Информативное сообщение в логе.
        except ftplib.error_perm:
            try:
                # Пытаемся создать текущую директорию.
                ftp.mkd(current)
                log(
                    "CREATED", f"Создана директория: {current}"
                )  # Информативное сообщение о создании.
            except ftplib.error_perm as e:
                # Обрабатываем ошибку создания директории.
                error_code = str(e).split(None, 1)[0]  # Извлекаем код ошибки.
                if (
                    error_code != "550"
                ):  # "550" - ошибка "File exists", которую мы игнорируем.
                    log(
                        "ERROR", f"Ошибка создания директории {current}: {e}"
                    )  # Подробное сообщение об ошибке.
                    return (
                        False  # Возвращаем False при ошибке, отличной от "File exists".
                    )
                else:
                    log(
                        "DEBUG", f"Директория {current} уже существует."
                    )  # Сообщение о существующей директории.
    return True  # Возвращаем True, если все директории успешно созданы.


def validate_dirs(dirs):
    """
    Оптимизирует список директорий, удаляя вложенные дубликаты.

    Алгоритм:
    1. Преобразует входные данные в список, если это строка.
    2. Сортирует список директорий по длине пути (от коротких к длинным).
    3. Итерируется по отсортированному списку и проверяет,
       начинается ли текущая директория с уже добавленных.
    4. Добавляет директорию в результат, если она не является вложенной.

    Пример:
        Вход: ["data", "data/test", "scripts", "scripts/utils"]
        Выход: ["data", "scripts"]
    Параметры:
        dirs (str/list): Список или строка с путями через запятую
    Возвращает:
        list: Оптимизированный список уникальных директорий
    """

    # Преобразуем входные данные в список, если это строка
    if isinstance(dirs, str):
        dirs = [path.strip() for path in dirs.split(",") if path.strip()]
        log("DEBUG", f"Преобразован строковый список в массив: {dirs}")
    # Сортируем список директорий по длине пути (от коротких к длинным)
    dirs.sort(key=len)  # Сортировка на месте
    valid_dirs = []  # Список для хранения "родительских" директорий
    removed_dirs = []  # Список для хранения удаленных "дочерних" директорий
    for dir_path in dirs:
        is_nested = False  # Флаг, показывающий, что текущая директория вложена в другую
        log("DEBUG", f"Проверка директории: {dir_path}")
        for existing_dir in valid_dirs:  # Теперь итерируемся по valid_dirs напрямую
            if dir_path.startswith(existing_dir + "/"):
                is_nested = True
                log("DEBUG", f"Директория {dir_path} вложена в {existing_dir}")
                break  # Выходим из внутреннего цикла, если нашли вложенность
        if not is_nested:  # Если директория не вложена, добавляем её
            valid_dirs.append(dir_path)
            log("DEBUG", f"Добавлена директория: {dir_path}")
        else:
            removed_dirs.append(dir_path)  # Иначе, добавляем в список удаленных
    if removed_dirs:
        log("DEBUG", f"Удалены вложенные дубликаты: {', '.join(removed_dirs)}")

    return valid_dirs


def normalize_items(items, script_dir, local_dir):
    """
    Нормализует пути, обрабатывая многоточечную нотацию ('..', '...') и преобразуя их в относительные.
    Если путь начинается с другого диска, используется абсолютный путь.

    Args:
        items (str): Строка, содержащая пути, разделенные запятыми.
        script_dir (str): Директория, в которой выполняется скрипт.
        local_dir (str): Базовая директория, относительно которой вычисляются относительные пути.
    Returns:
        dict: Словарь с нормализованными путями для файлов и директорий.
    """
    if not items or items.isspace():
        return {
            "files": [],
            "dirs": [],
        }  # Возвращаем пустые списки для файлов и директорий.

    result = {
        "files": [],
        "dirs": [],
    }  # Словарь для хранения нормализованных файлов и директорий.

    # Разделяем входную строку на отдельные пути, разделенные запятыми.
    for item in items.split(","):
        item = item.strip()  # Удаляем лишние пробелы в начале и конце пути.

        # Пропускаем пустые пути.
        if not item:
            continue

        try:
            # Разрешаем абсолютный путь, используя функцию resolve_path (предполагается, что она определена ранее).
            abs_path = resolve_path(item, script_dir)

            # Проверяем, находятся ли пути на одном и том же диске.
            if (
                os.path.splitdrive(abs_path)[0].lower()
                != os.path.splitdrive(local_dir)[0].lower()
            ):
                # Если путь на другом диске, используем абсолютный путь.
                final_path = abs_path.replace("\\", "/").lower()
                log(
                    "INFO",
                    f"Путь {abs_path} находится на другом диске. Используется абсолютный путь.",
                )
                # Проверяем существование файла по абсолютному пути.
                if os.path.exists(final_path):
                    # Если путь существует, добавляем в список файлов или директорий в зависимости от типа.
                    if os.path.isdir(final_path):
                        result["dirs"].append(final_path)
                    else:
                        result["files"].append(final_path)
                else:
                    log("ERROR", f"Путь не существует: {final_path}")
            else:
                # Если путь на том же диске, вычисляем относительный путь.
                rel_path = os.path.relpath(abs_path, local_dir)
                normalized = rel_path.replace("\\", "/").lower()

                # Обрабатываем специальные последовательности '..' и '...' (для перехода на уровни вверх).
                parts = normalized.split("/")  # Разделяем путь на компоненты.
                processed = []  # Список для хранения обработанных компонентов пути.

                for part in parts:
                    if part == "..":  # Обработка перехода на уровень выше.
                        if processed:
                            processed.pop()  # Удаляем последний компонент из processed.
                        else:
                            log("WARNING", f"Некорректный переход выше корня: {item}")
                            break  # Прерываем обработку текущего пути.
                    elif part.startswith(
                        "..."
                    ):  # Обработка удаления нескольких уровней.
                        # Вычисляем количество уровней для удаления (количество точек минус 1).
                        levels = len(part) - 1
                        # Удаляем соответствующее количество уровней из processed, если возможно.
                        processed = (
                            processed[:-levels] if len(processed) >= levels else []
                        )
                    else:
                        processed.append(part)  # Добавляем компонент пути в processed.

                # Собираем обработанный путь обратно в строку.
                final_path = "/".join(processed)
                # Проверяем, что final_path не пустой после обработки.
                if final_path:
                    # Проверяем существование файла по нормализованному пути относительно local_dir.
                    full_path = os.path.join(local_dir, final_path)
                    if os.path.exists(full_path):
                        # Если файл существует, добавляем его в соответствующий список.
                        if os.path.isdir(full_path):
                            result["dirs"].append(final_path)
                        else:
                            result["files"].append(final_path)
                    else:
                        log("ERROR", f"Путь не существует: {final_path}")
                else:
                    log(
                        "WARNING",
                        f"Недопустимый путь: {item} (преобразован в пустую строку)",
                    )

        except ValueError as e:
            log("WARNING", f"Ошибка обработки пути {item}: {e}")

    return (
        result  # Возвращаем словарь с нормализованными путями для файлов и директорий.
    )


def should_upload_file(file_path, local_dir, excluded_files, excluded_extensions):
    """
    Определяет, нужно ли загружать файл на основе списка исключенных файлов и расширений.

    Args:
        file_path (str): Абсолютный путь к файлу.
        local_dir (str): Локальная директория, относительно которой вычисляется относительный путь.
        excluded_files (list): Список относительных путей к файлам, которые не нужно загружать.
        excluded_extensions (list): Список расширений файлов, которые не нужно загружать.
    Returns:
        bool: True, если файл нужно загружать, False, если файл нужно исключить.
    """
    # Получаем относительный путь к файлу относительно local_dir, приводим к нижнему регистру и заменяем обратные слеши на прямые.
    rel_path = os.path.relpath(file_path, local_dir).replace("\\", "/").lower()
    # Получаем расширение файла и приводим его к нижнему регистру.
    file_ext = os.path.splitext(file_path)[1].lower()
    # Логируем информацию о проверяемом файле.
    log("DEBUG", f"Проверка файла: {rel_path}")
    log("DEBUG", f"Расширение файла: {file_ext}")
    # Проверяем, находится ли относительный путь файла в списке исключенных файлов.
    if rel_path in excluded_files:
        # Если файл найден в списке исключенных, логируем это и возвращаем False (не загружать).
        log("DEBUG", f"Файл исключен: {rel_path} (в списке исключенных файлов)")
        return False
    # Проверяем, находится ли расширение файла в списке исключенных расширений.
    if file_ext in excluded_extensions:
        # Если расширение файла найдено в списке исключенных, логируем это и возвращаем False (не загружать).
        log(
            "DEBUG",
            f"Файл исключен: {rel_path} (расширение {file_ext} в списке исключений)",
        )
        return False
    # Если файл не попал ни в один из списков исключений, логируем это и возвращаем True (загружать).
    log("DEBUG", f"Файл будет загружен: {rel_path}")
    return True


def upload_with_progress(
    ftp, local_path, remote_path, filesize, retry_count=MAX_RETRIES
):
    """
    Загружает файл с визуализацией прогресса и повторными попытками.

    Параметры:
        ftp (ftplib.FTP): FTP-соединение
        local_path (str): Локальный путь к файлу
        remote_path (str): Целевой путь на сервере
        filesize (int): Размер файла в байтах
        retry_count (int): Количество оставшихся попыток при ошибках
    Возвращает:
        bool: True при успешной загрузке
    """
    pbar = None

    try:
        if not ftp.sock:
            log("WARNING", "Переподключение к серверу...")
            ftp.connect(FTP_HOST)
            ftp.login(FTP_USER, FTP_PASS)

        with open(local_path, "rb") as f:
            if filesize > 102400:
                log("SUCCESS", f"Загрузка {os.path.basename(local_path)} начата...")

                pbar = tqdm(
                    total=filesize,
                    unit="B",
                    unit_scale=True,
                    desc=os.path.basename(remote_path),
                    position=0,
                    leave=True,
                    ncols=100,
                    ascii=False,
                )

            def update_progress(data):
                """Callback для обновления прогресса"""
                if pbar:
                    pbar.update(len(data))

            try:
                ftp.storbinary(f"STOR {remote_path}", f, callback=update_progress)
                success = True
            except ftplib.error_temp as e:
                if retry_count > 0:
                    log(
                        "WARNING",
                        f"Ошибка: {e}. Повторная попытка через {RETRY_DELAY} сек. ({retry_count} осталось)...",
                    )
                    time.sleep(RETRY_DELAY)
                    return upload_with_progress(
                        ftp, local_path, remote_path, filesize, retry_count - 1
                    )
                else:
                    log("ERROR", "Превышено количество попыток загрузки")
                    success = False
            except Exception as e:
                log("ERROR", f"Критическая ошибка: {str(e)}")
                success = False
    finally:
        if pbar:
            pbar.close()

        if success:
            log(
                "SUCCESS", f"Загрузка {os.path.basename(local_path)} завершена."
            )  # Лог после закрытия pbar

    return success


# Функция для проверки существования файла на сервере
def file_exists_on_ftp(ftp, path):
    try:
        # Пытаемся получить список файлов в текущей директории
        ftp.cwd(os.path.dirname(path))  # Переходим в директорию файла
        files = ftp.nlst()  # Получаем список файлов в директории
        return os.path.basename(path) in files  # Проверяем, существует ли файл
    except Exception:
        return False  # Если произошла ошибка, значит файл не существуе


def main():
    """
    Основная функция, управляющая процессом загрузки файлов на FTP-сервер.

    Выполняет следующие шаги:
    1. Настройка логирования.
    2. Проверка доступности сервера.
    3. Подготовка списков исключений (файлов и директорий).
    4. Рекурсивный обход локальной директории.
    5. Загрузка файлов на FTP-сервер с обработкой ошибок.
    """

    # Настройка логирования.  Указываем, какие типы сообщений выводить.
    # Раскомментируйте нужные строки для включения/отключения типов сообщений.
    # LOG_SETTINGS["LEVELS"]["DEBUG"] = True  # Отладочная информация
    LOG_SETTINGS["LEVELS"]["INFO"] = True  # Информационные сообщения
    LOG_SETTINGS["LEVELS"]["WARNING"] = True  # Предупреждения
    LOG_SETTINGS["LEVELS"]["ERROR"] = True  # Ошибки
    LOG_SETTINGS["LEVELS"]["SUCCESS"] = True  # Сообщения об успешном выполнении
    LOG_SETTINGS["LEVELS"]["SKIPPED"] = True  # Пропущенные элементы
    LOG_SETTINGS["LEVELS"]["CREATED"] = True  # Созданные объекты (например, директории)

    # Полностью отключить логирование:
    # LOG_SETTINGS['ENABLED'] = False

    # Проверка доступности FTP-сервера.
    if not is_server_available(
        FTP_HOST
    ):  # Функция is_server_available должна быть определена ранее.
        log("ERROR", f"FTP-сервер {FTP_HOST} недоступен.")
        input("\nНажмите Enter для выхода...")  # Ожидаем нажатия Enter для выхода.
        return  # Завершаем выполнение функции.

    # Определение директорий и путей.
    script_dir = os.getcwd()  # Текущая директория, где выполняется скрипт.
    local_dir = os.path.abspath(resolve_path(LOCAL_DIR)) if LOCAL_DIR else script_dir
    # Если LOCAL_DIR задана, преобразуем ее в абсолютный путь. Иначе используем script_dir.

    # Нормализация списков исключений.
    excluded_extensions = [
        ext.strip().lower() for ext in EXCLUDED_EXTENSIONS.split(",") if ext.strip()
    ]

    # Список исключенных расширений файлов. Приводим к нижнему регистру и удаляем пробелы.
    EXCLUDED = normalize_items(EXCLUDED_FILES_DIRS, script_dir, local_dir)
    excluded_files = EXCLUDED["files"]  # Список исключенных файлов.
    excluded_dirs = EXCLUDED["dirs"]  # Список исключенных директорий.
    # Оптимизируем список исключенных директорий, удаляя вложенные.

    # Логирование для отладки. Выводим значения переменных для проверки.
    log("DEBUG", f"Директория скрипта: {script_dir}")
    log("DEBUG", f"Локальная директория: {local_dir}")
    log("DEBUG", f"Исключенные расширения: {excluded_extensions}")
    log("DEBUG", f"Исходный список исключений: {EXCLUDED_FILES_DIRS}")
    log("DEBUG", f"Исключенные директории после нормализации: {excluded_dirs}")
    log("DEBUG", f"Исключенные файлы после валидации: {excluded_files}")

    # Проверка существования локальной директории.
    if not os.path.isdir(local_dir):
        log("ERROR", f"Директория {local_dir} не существует!")
        input("\nНажмите Enter для выхода...")
        return
    # Подключение к FTP-серверу.
    try:
        ftp = ftplib.FTP(
            host=FTP_HOST, encoding=ENCODING
        )  # Создаем объект FTP-соединения.
        ftp.login(FTP_USER, FTP_PASS)  # Авторизуемся на сервере.
        ftp.set_pasv(True)  # Переключаемся в пассивный режим передачи данных.
        log("INFO", f"Успешно подключено к {FTP_HOST}")
    except Exception as e:
        log("ERROR", f"Ошибка подключения: {e}")
        input("\nНажмите Enter для выхода...")
        return
    # Рекурсивный обход локальной директории и загрузка файлов.
    for root, dirs, files in os.walk(
        local_dir
    ):  # os.walk возвращает корневую директорию, список поддиректорий и список файлов.
        rel_path = os.path.relpath(
            root, local_dir
        )  # Относительный путь к текущей директории.
        remote_path = normalize_path(
            os.path.join(REMOTE_DIR, rel_path)
        )  # Формируем путь на FTP-сервере.
        if remote_path == "/":
            remote_path = ""  # Избегаем двойного слеша в начале пути.
        # Проверяем, является ли текущая директория исключенной.
        if is_excluded_dir(
            rel_path, excluded_dirs
        ):  # Проверяем, исключена ли текущая директория.
            log("SKIPPED", f"Директория {rel_path} находится в списке исключений")
            continue  # Переходим к следующей итерации цикла.
        # Создание директории на сервере (если её нет).
        if not ensure_remote_dir(
            ftp, remote_path
        ):  # Создаем директорию на FTP-сервере.
            log("SKIPPED", f"Пропуск директории {remote_path} из-за ошибки создания")
            continue  # Переходим к следующей итерации цикла.
        # Загрузка файлов.
        for file in files:
            file_path = os.path.join(root, file)  # Полный путь к файлу.
            if not should_upload_file(
                file_path, local_dir, excluded_files, excluded_extensions
            ):
                log("SKIPPED", f"{file_path}")  # Проверяем, нужно ли загружать файл.
                continue  # Переходим к следующему файлу.
            # Попытки загрузки файла с повторными попытками.
            for attempt in range(MAX_RETRIES):
                try:
                    remote_file_path = normalize_path(os.path.join(remote_path, file))
                    ftp.cwd("/")  # Переходим в корень FTP-сервера.
                    if remote_path:
                        ftp.cwd(
                            remote_path
                        )  # Переходим в нужную директорию на FTP-сервере.

                    # Проверяем, существует ли файл на сервере
                    if file_exists_on_ftp(ftp, remote_file_path):
                        log(
                            "DEBUG",
                            f"Файл {remote_file_path} существует, удаляем перед загрузкой.",
                        )
                        ftp.delete(remote_file_path)  # Удаляем файл на сервере.

                    filesize = os.path.getsize(file_path)  # Размер файла.
                    start_time = time.time()  # Время начала загрузки.

                    # Загрузка файла с прогресс-баром (для больших файлов).
                    if (
                        filesize > MIN_FILESIZE_FOR_PROGRESSBAR
                    ):  # Если размер файла больше MIN_FILESIZE_FOR_PROGRESSBAR используем прогресс-бар.
                        log("DEBUG", f"Начало загрузки файла: {remote_file_path}")
                        success = upload_with_progress(
                            ftp, file_path, remote_file_path, filesize
                        )  # Загрузка с прогресс-баром.
                        if not success:
                            log("WARNING", f"Пропуск файла из-за ошибок: {file_path}")
                            continue  # Переходим к следующему файлу.
                    else:
                        with open(
                            file_path, "rb"
                        ) as f:  # Открываем файл в бинарном режиме для чтения.
                            ftp.storbinary(
                                f"STOR {file}", f
                            )  # Загружаем файл на FTP-сервер.

                    # Устанавливаем права 777 для файла после загрузки
                    # ftp.sendcmd(f"SITE CHMOD 777 {remote_file_path}") // если не разрешено то сбой

                    try:
                        ftp.sendcmd(f"SITE CHMOD 777 {remote_file_path}")
                        log(
                            "DEBUG",
                            f"Успешно установлены права 777 для {remote_file_path}",
                        )
                    except Exception as e:
                        log(
                            "DEBUG",
                            f"Ошибка при установке прав 777 для {remote_file_path}: {e}",
                        )
                        try:
                            ftp.sendcmd(f"SITE CHMOD 755 {remote_file_path}")
                            log(
                                "DEBUG",
                                f"Успешно установлены права 755 для {remote_file_path}",
                            )
                        except Exception as e2:
                            log(
                                "DEBUG",
                                f"Ошибка при установке прав 755 для {remote_file_path}: {e2}",
                            )
                            try:
                                ftp.sendcmd(f"SITE CHMOD 644 {remote_file_path}")
                                log(
                                    "DEBUG",
                                    f"Успешно установлены права 644 для {remote_file_path}",
                                )
                            except Exception as e3:
                                log(
                                    "DEBUG",
                                    f"Ошибка при установке прав 644 для {remote_file_path}: {e3}",
                                )
                                log(
                                    "ERROR",
                                    f"Не удалось изменить права для {remote_file_path}, возможно, сервер запрещает это.",
                                )

                    elapsed_time = (
                        time.time() - start_time
                    )  # Время, затраченное на загрузку.
                    log(
                        "SUCCESS",
                        f"{remote_file_path} ({filesize} байт, {elapsed_time:.2f} сек)",
                    )  # Сообщение об успешной загрузке.
                    break  # Выходим из цикла попыток, если загрузка успешна.

                except Exception as e:
                    log(
                        "ERROR", f"{file_path} - {str(e)} (попытка {attempt + 1})"
                    )  # Обработка ошибок загрузки.
                    if attempt < MAX_RETRIES - 1:
                        time.sleep(RETRY_DELAY)  # Ждем перед следующей попыткой.
                    else:
                        log(
                            "ERROR",
                            f"Не удалось загрузить файл {file_path} после {MAX_RETRIES} попыток.",
                        )

    # Завершение работы.
    if ftp:  # Проверяем, было ли установлено соединение, прежде чем закрывать его.
        ftp.quit()  # Закрываем соединение с FTP-сервером.
    log("INFO", "Загрузка завершена!")
    # Запрос Нажмите Enter для выхода...и
    input("Нажмите Enter для выхода...")


if __name__ == "__main__":
    main()
