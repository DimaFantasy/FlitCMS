<?php

interface BaseTableStorage
{
    public function createTable(string $tableName, array $columns): bool;
    public function deleteTable(string $tableName): bool;
    public function getAllRecords(string $tableName): array;
    public function getRecordsByColumn(string $tableName, string $column, $value, bool $caseSensitive = true): array;
    public function insertRecord(string $tableName, array $data): bool;
    public function updateRecord(string $tableName, array $data, array $dataUpdate): int;
    public function deleteRecord(string $tableName, array $data): int;
}

/**
 * Проверка имён таблиц, колонок и баз данных.
 * Эти имена подставляются в SQL и в пути к файлам, поэтому допускаем только буквы, цифры и "_".
 *
 * @throws InvalidArgumentException если имя недопустимо
 */
function assertIdentifier($name, string $what = 'identifier'): string
{
    if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
        throw new InvalidArgumentException("Недопустимое имя ($what)");
    }
    return $name;
}

class JsonTableStorage implements BaseTableStorage
{

    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $directory = $this->config['path'];

        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        // Внутри лежат хеши паролей: закрываем папку от прямого доступа из браузера
        protectDirectory($directory);
    }

    private function getTablePath(string $tableName): string
    {
        assertIdentifier($tableName, 'table');
        return $this->config['path'] . '/' . $tableName . '.json';
    }

    /**
     * Читает таблицу. Возвращает null, если файла нет или он повреждён.
     */
    private function readTable(string $tablePath): ?array
    {
        $raw = @file_get_contents($tablePath);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['columns']) || !is_array($data['columns'])) {
            return null;
        }
        if (!isset($data['records']) || !is_array($data['records'])) {
            $data['records'] = [];
        }
        return $data;
    }

    /**
     * Записывает таблицу целиком: во временный файл, затем переименование,
     * чтобы при сбое посреди записи не потерять данные.
     */
    private function writeTable(string $tablePath, array $tableData, int $flags = 0): bool
    {
        $json = json_encode($tableData, $flags | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        $tmp = $tablePath . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) !== false && @rename($tmp, $tablePath)) {
            return true;
        }
        // Запасной путь (например, Windows, когда файл занят другим процессом)
        @unlink($tmp);
        return file_put_contents($tablePath, $json, LOCK_EX) !== false;
    }

    /**
     * Выполняет "прочитать - изменить - записать" под эксклюзивной блокировкой,
     * чтобы два одновременных запроса не затирали изменения друг друга.
     *
     * @param callable $fn function (array &$tableData): array  Возвращает [результат, нужно_ли_записывать]
     * @return mixed Результат, вернувшийся из $fn
     */
    private function mutate(string $tableName, callable $fn)
    {
        $tablePath = $this->getTablePath($tableName);
        $lock = fopen($tablePath . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException("Не удалось открыть блокировку таблицы $tableName");
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException("Не удалось заблокировать таблицу $tableName");
            }
            $tableData = $this->readTable($tablePath);
            if ($tableData === null) {
                // Не перезаписываем повреждённый или отсутствующий файл пустой таблицей
                throw new RuntimeException("Таблица $tableName недоступна или повреждена");
            }
            [$result, $needWrite] = $fn($tableData);
            if ($needWrite && !$this->writeTable($tablePath, $tableData, JSON_PRETTY_PRINT)) {
                throw new RuntimeException("Не удалось записать таблицу $tableName");
            }
            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function createTable(string $tableName, array $columns): bool
    {
        $tablePath = $this->getTablePath($tableName);
        foreach (array_keys($columns) as $column) {
            assertIdentifier((string)$column, 'column');
        }

        // Если таблица уже существует, возвращаем true
        if (file_exists($tablePath)) {
            return true;
        }

        // Извлекаем только имена столбцов (игнорируем типы данных)
        $columnNames = array_keys($columns);

        // Структура таблицы
        $structure = [
            'columns' => $columnNames, // Сохраняем только имена столбцов
            'records' => []
        ];

        // Пытаемся создать таблицу и возвращаем результат
        $isCreated = $this->writeTable($tablePath, $structure);

        // Логирование результата
        if ($isCreated) {
            logMessage("Таблица $tableName создана", 'INFO');
        }

        return $isCreated;
    }

    public function deleteTable(string $tableName): bool
    {
        $tablePath = $this->getTablePath($tableName);
        if (file_exists($tablePath)) {
            unlink($tablePath);
            logMessage("Таблица $tableName удалена", 'INFO');
            return true;
        }
        return false;
    }

    public function insertRecord(string $tableName, array $data): bool
    {
        $result = $this->mutate($tableName, function (array &$tableData) use ($tableName, $data) {
            $columns = $tableData['columns'];

            foreach ($data as $key => $value) {
                if (!in_array($key, $columns, true)) {
                    logMessage("Поле $key не существует в таблице $tableName", 'ERROR');
                    return [false, false];
                }
            }

            $record = array_fill_keys($columns, null); // Заполняем массив значениями по умолчанию
            foreach ($data as $key => $value) {
                $record[$key] = $value;
            }

            // Добавляем запись в массив, а не в объект
            $tableData['records'][] = $record;
            return [true, true];
        });

        if ($result) {
            logMessage("Запись добавлена в таблицу $tableName", 'INFO');
        }
        return (bool)$result;
    }

    public function getAllRecords(string $tableName): array
    {
        $tableData = $this->readTable($this->getTablePath($tableName));
        return $tableData['records'] ?? [];
    }

    public function getRecordsByColumn(string $tableName, string $column, $value, bool $caseSensitive = true): array
    {
        $records = $this->getAllRecords($tableName);

        return array_filter($records, function ($record) use ($column, $value, $caseSensitive) {
            if (!isset($record[$column])) return false;

            return $caseSensitive
                ? $record[$column] === $value
                : strtolower((string)$record[$column]) === strtolower((string)$value);
        });
    }

    public function updateRecord(string $tableName, array $data, array $dataUpdate): int
    {
        // Без условий или без изменений ничего не делаем (иначе обновились бы ВСЕ записи)
        if (empty($data) || empty($dataUpdate)) {
            return 0;
        }

        $updatedCount = $this->mutate($tableName, function (array &$tableData) use ($tableName, $data, $dataUpdate) {
            $columns = $tableData['columns'];

            foreach (array_merge(array_keys($dataUpdate), array_keys($data)) as $key) {
                if (!in_array($key, $columns, true)) {
                    logMessage("Поле $key не существует в таблице $tableName", 'ERROR');
                    return [0, false];
                }
            }

            $count = 0;
            foreach ($tableData['records'] as &$record) {
                // Все условия должны совпасть (как WHERE a = ? AND b = ? в MySQL)
                $match = true;
                foreach ($data as $key => $value) {
                    if (!array_key_exists($key, $record) || $record[$key] !== $value) {
                        $match = false;
                        break;
                    }
                }

                if ($match) {
                    foreach ($dataUpdate as $key => $value) {
                        $record[$key] = $value;
                    }
                    $count++;
                }
            }
            unset($record);

            return [$count, $count > 0];
        });

        if ($updatedCount > 0) {
            logMessage("Записи обновлены в таблице $tableName", 'INFO');
        }
        return $updatedCount;
    }

    public function deleteRecord(string $tableName, array $data): int
    {
        // Без условий не удаляем ничего
        if (empty($data)) {
            return 0;
        }

        $removed = $this->mutate($tableName, function (array &$tableData) use ($tableName, $data) {
            $columns = $tableData['columns'];

            foreach ($data as $key => $value) {
                if (!in_array($key, $columns, true)) {
                    logMessage("Поле $key не существует в таблице $tableName", 'ERROR');
                    return [0, false];
                }
            }

            $originalCount = count($tableData['records']);
            // Удаляем запись, только если совпали ВСЕ условия (как в MySQL); array_values сохраняет JSON-список
            $tableData['records'] = array_values(array_filter($tableData['records'], function ($record) use ($data) {
                foreach ($data as $key => $value) {
                    if (!array_key_exists($key, $record) || $record[$key] !== $value) {
                        return true; // Условие не совпало - оставляем запись
                    }
                }
                return false; // Совпали все условия - удаляем
            }));

            $diff = $originalCount - count($tableData['records']);
            return [$diff, $diff > 0];
        });

        if ($removed > 0) {
            logMessage("Записи удалены из таблицы $tableName", 'INFO');
        }
        return $removed;
    }
}

class MysqlTableStorage implements BaseTableStorage
{
    private $pdo;

    public function __construct(array $config)
    {
        try {
            $host    = $config['mysql']['host'];
            $charset = assertIdentifier($config['mysql']['charset'], 'charset');
            $dbName  = assertIdentifier($config['mysql']['dbname'], 'database');

            // Подключаемся к серверу MySQL без указания базы данных
            $this->pdo = new PDO(
                "mysql:host={$host};charset={$charset}",
                $config['mysql']['user'],
                $config['mysql']['password']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Настоящие подготовленные запросы (значения не подставляются в SQL на стороне PHP)
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            // Проверяем существование базы данных
            $stmt = $this->pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :dbname");
            $stmt->execute([':dbname' => $dbName]);

            // Если база данных не существует, создаем её
            if ($stmt->rowCount() == 0) {
                $this->pdo->exec("CREATE DATABASE `$dbName`");
                logMessage("База данных $dbName создана", 'INFO');
            }

            // Переключаемся на созданную или существующую базу данных
            $this->pdo->exec("USE `$dbName`");
        } catch (PDOException $e) {
            logMessage("Ошибка при создании базы данных: " . $e->getMessage(), 'ERROR');
            throw new RuntimeException("Не удалось создать или подключиться к базе данных.");
        }
    }

    /** Имя таблицы/колонки в обратных кавычках после проверки */
    private function ident($name, string $what = 'identifier'): string
    {
        return '`' . assertIdentifier($name, $what) . '`';
    }

    public function createTable(string $tableName, array $columns): bool
    {
        $table = $this->ident($tableName, 'table');

        // Проверяем, существует ли таблица (точное совпадение имени, без шаблонов LIKE)
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$tableName]);

        if ($stmt->fetch()) {
            // Если таблица существует, возвращаем true
            return true;
        }

        // Определяем типы данных для каждого столбца
        $columnDefinitions = [];
        foreach ($columns as $column => $type) {
            // Имя колонки проверяем; тип приходит из кода CMS, а не от пользователя
            $columnDefinitions[] = $this->ident((string)$column, 'column') . " $type";
        }

        // Создаём запрос для создания таблицы
        $sql = "CREATE TABLE $table (" . implode(', ', $columnDefinitions) . ")";

        // Выполняем запрос
        try {
            $this->pdo->exec($sql);
            logMessage("Таблица $tableName создана", 'INFO');
            return true;
        } catch (PDOException $e) {
            logMessage("Ошибка при создании таблицы $tableName: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function deleteTable(string $tableName): bool
    {
        $sql = "DROP TABLE IF EXISTS " . $this->ident($tableName, 'table');
        logMessage("Таблица $tableName удалена", 'INFO');
        return $this->pdo->exec($sql) !== false;
    }

    public function insertRecord(string $tableName, array $data): bool
    {
        if (empty($data)) {
            return false;
        }
        $columns = implode(', ', array_map(fn($c) => $this->ident((string)$c, 'column'), array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO " . $this->ident($tableName, 'table') . " ($columns) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute(array_values($data));
        logMessage("Запись добавлена в таблицу $tableName", 'INFO');
        return $result;
    }

    public function getAllRecords(string $tableName): array
    {
        $sql = "SELECT * FROM " . $this->ident($tableName, 'table');
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function getRecordsByColumn(string $tableName, string $column, $value, bool $caseSensitive = true): array
    {
        $col = $this->ident($column, 'column');
        // Раньше здесь был LIKE '%значение%' - поиск по подстроке и подстановочные знаки (% и _).
        // Теперь точное совпадение: с учётом регистра (BINARY) или без учёта (LOWER) - как в JSON-хранилище.
        $condition = $caseSensitive ? "BINARY $col = ?" : "LOWER($col) = LOWER(?)";
        $sql = "SELECT * FROM " . $this->ident($tableName, 'table') . " WHERE $condition";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateRecord(string $tableName, array $data, array $dataUpdate): int
    {
        // Без условий или без изменений ничего не делаем (иначе обновились бы ВСЕ записи)
        if (empty($data) || empty($dataUpdate)) {
            return 0;
        }

        $setClause = [];
        foreach ($dataUpdate as $key => $value) {
            $setClause[] = $this->ident((string)$key, 'column') . " = ?";
        }

        $whereClause = [];
        foreach ($data as $key => $value) {
            $whereClause[] = $this->ident((string)$key, 'column') . " = ?";
        }

        $sql = "UPDATE " . $this->ident($tableName, 'table') . " SET " . implode(', ', $setClause) .
            " WHERE " . implode(' AND ', $whereClause);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge(array_values($dataUpdate), array_values($data)));
        logMessage("Записи обновлены в таблице $tableName", 'INFO');
        return $stmt->rowCount();
    }

    public function deleteRecord(string $tableName, array $data): int
    {
        // Без условий не удаляем ничего
        if (empty($data)) {
            return 0;
        }

        $whereClause = [];
        foreach ($data as $key => $value) {
            $whereClause[] = $this->ident((string)$key, 'column') . " = ?";
        }

        $sql = "DELETE FROM " . $this->ident($tableName, 'table') . " WHERE " . implode(' AND ', $whereClause);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        logMessage("Записи удалены из таблицы $tableName", 'INFO');
        return $stmt->rowCount();
    }
}
