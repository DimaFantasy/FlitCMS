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

class JsonTableStorage implements BaseTableStorage
{

    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $directory = $this->config['path'];

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    private function getTablePath(string $tableName): string
    {
        return $this->config['path'] . '/' . $tableName . '.json';
    }

    public function createTable(string $tableName, array $columns): bool
    {
        $tablePath = $this->getTablePath($tableName);

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
        $isCreated = (bool)file_put_contents($tablePath, json_encode($structure, JSON_UNESCAPED_UNICODE));

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
        $tablePath = $this->getTablePath($tableName);
        $tableData = json_decode(file_get_contents($tablePath), true);
        $columns = $tableData['columns'];

        foreach ($data as $key => $value) {
            if (!in_array($key, $columns)) {
                logMessage("Поле $key не существует в таблице $tableName", 'ERROR');
                return false;
            }
        }

        $record = array_fill_keys($columns, null); // Заполняем массив значениями по умолчанию
        foreach ($data as $key => $value) {
            $record[$key] = $value;
        }

        // Добавляем запись в массив, а не в объект
        $tableData['records'][] = $record;

        logMessage("Запись добавлена в таблицу $tableName", 'INFO');
        return (bool)file_put_contents($tablePath, json_encode($tableData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function getAllRecords(string $tableName): array
    {
        $tablePath = $this->getTablePath($tableName);
        return json_decode(file_get_contents($tablePath), true)['records'] ?? [];
    }

    public function getRecordsByColumn(string $tableName, string $column, $value, bool $caseSensitive = true): array
    {
        $records = $this->getAllRecords($tableName);

        return array_filter($records, function ($record) use ($column, $value, $caseSensitive) {
            if (!isset($record[$column])) return false;

            return $caseSensitive
                ? $record[$column] === $value
                : strtolower($record[$column]) === strtolower($value);
        });
    }

    public function updateRecord(string $tableName, array $data, array $dataUpdate): int
    {
        $tablePath = $this->getTablePath($tableName);
        $tableData = json_decode(file_get_contents($tablePath), true);
        $updatedCount = 0;
        $columns = $tableData['columns'];

        foreach ($dataUpdate as $key => $value) {
            if (!in_array($key, $columns)) {
                logMessage("Поле $key не существует в таблице $tableName", 'ERROR');
                return 0;
            }
        }

        foreach ($tableData['records'] as &$record) {
            $match = true;
            foreach ($data as $key => $value) {
                if (isset($record[$key]) && $record[$key] !== $value) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                foreach ($dataUpdate as $key => $value) {
                    $record[$key] = $value;
                }
                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            logMessage("Записи обновлены в таблице $tableName", 'INFO');
            file_put_contents($tablePath, json_encode($tableData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        return $updatedCount;
    }

    public function deleteRecord(string $tableName, array $data): int
    {
        $tablePath = $this->getTablePath($tableName);
        $tableData = json_decode(file_get_contents($tablePath), true);
        $originalCount = count($tableData['records']);
        $columns = $tableData['columns'];

        foreach ($data as $key => $value) {
            if (!in_array($key, $columns)) {
                logMessage("Поле $key не существует в таблице $tableName", 'ERROR');
                return 0;
            }
        }

        $tableData['records'] = array_filter($tableData['records'], function ($record) use ($data) {
            foreach ($data as $key => $value) {
                if (isset($record[$key]) && $record[$key] === $value) {
                    return false; // Удаляем запись, если она соответствует условиям
                }
            }
            return true; // Оставляем запись, если она не соответствует условиям
        });

        $newCount = count($tableData['records']);
        if ($newCount !== $originalCount) {
            logMessage("Записи удалены из таблицы $tableName", 'INFO');
            file_put_contents($tablePath, json_encode($tableData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        return $originalCount - $newCount;
    }
}

class MysqlTableStorage implements BaseTableStorage
{
    private $pdo;

    public function __construct(array $config)
    {
        try {
            // Подключаемся к серверу MySQL без указания базы данных
            $this->pdo = new PDO(
                "mysql:host={$config['mysql']['host']};charset={$config['mysql']['charset']}",
                $config['mysql']['user'],
                $config['mysql']['password']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $dbName = $config['mysql']['dbname']; // Используем имя базы данных из конфигурации

            // Проверяем существование базы данных
            $stmt = $this->pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :dbname");
            $stmt->execute([':dbname' => $dbName]);

            // Если база данных не существует, создаем её
            if ($stmt->rowCount() == 0) {
                $this->pdo->exec("CREATE DATABASE `" . addslashes($dbName) . "`");
                logMessage("База данных $dbName создана", 'INFO');
            } else {
                // logMessage("База данных $dbName уже существует", 'INFO');
            }

            // Переключаемся на созданную или существующую базу данных
            $this->pdo->exec("USE `" . addslashes($dbName) . "`");
        } catch (PDOException $e) {
            logMessage("Ошибка при создании базы данных: " . $e->getMessage(), 'ERROR');
            throw new RuntimeException("Не удалось создать или подключиться к базе данных.");
        }
    }

    public function createTable(string $tableName, array $columns): bool
    {
        // Проверяем, существует ли таблица
        $checkTableSql = "SHOW TABLES LIKE :tableName";
        $stmt = $this->pdo->prepare($checkTableSql);
        $stmt->execute(['tableName' => $tableName]);

        if ($stmt->fetch()) {
            // Если таблица существует, возвращаем true
            return true;
        }

        // Определяем типы данных для каждого столбца
        $columnDefinitions = [];
        foreach ($columns as $column => $type) {
            // Используем переданный тип данных для каждого столбца
            $columnDefinitions[] = "`$column` $type";
        }

        // Создаём запрос для создания таблицы
        $sql = "CREATE TABLE `$tableName` (" . implode(', ', $columnDefinitions) . ")";

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
        $sql = "DROP TABLE IF EXISTS $tableName";
        logMessage("Таблица $tableName удалена", 'INFO');
        return $this->pdo->exec($sql) !== false;
    }

    public function insertRecord(string $tableName, array $data): bool
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $tableName ($columns) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute(array_values($data));
        logMessage("Запись добавлена в таблицу $tableName", 'INFO');
        return $result;
    }

    public function getAllRecords(string $tableName): array
    {
        $sql = "SELECT * FROM $tableName";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function getRecordsByColumn(string $tableName, string $column, $value, bool $caseSensitive = true): array
    {
        $operator = $caseSensitive ? '=' : 'LIKE';
        $sql = "SELECT * FROM $tableName WHERE $column $operator ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$caseSensitive ? $value : "%$value%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateRecord(string $tableName, array $data, array $dataUpdate): int
    {
        $setClause = [];
        foreach ($dataUpdate as $key => $value) {
            $setClause[] = "$key = ?";
        }

        $whereClause = [];
        foreach ($data as $key => $value) {
            $whereClause[] = "$key = ?";
        }

        $sql = "UPDATE $tableName SET " . implode(', ', $setClause) . " WHERE " . implode(' AND ', $whereClause);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge(array_values($dataUpdate), array_values($data)));
        logMessage("Записи обновлены в таблице $tableName", 'INFO');
        return $stmt->rowCount();
    }

    public function deleteRecord(string $tableName, array $data): int
    {
        $whereClause = [];
        foreach ($data as $key => $value) {
            $whereClause[] = "$key = ?";
        }

        $sql = "DELETE FROM $tableName WHERE " . implode(' AND ', $whereClause);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        logMessage("Записи удалены из таблицы $tableName", 'INFO');
        return $stmt->rowCount();
    }
}
