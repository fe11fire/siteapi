<?php

namespace SiteApi\Root\Helpers;

use PDO;
use Exception;
use SiteApi\Root\Helpers\LogHelper;
use SiteApi\Root\Settings\Settings;

class DBHelper
{
    const DB_PREFIX = '';

    private static ?PDO $connection = null;

    /**
     * Выбирает ровно одну строку; отсутствие возвращает false, несколько строк — исключение.
     *
     * Выборка загружается целиком, автоматический LIMIT не добавляется.
     * SQL-структура и имена колонок должны быть доверенными; значения where связываются отдельно.
     * @param string $columns SQL-список колонок или *.
     * @param array<string> $table Таблицы с необязательными SQL-псевдонимами.
     * @param string $beforeWhere Доверенный SQL до WHERE, например JOIN.
     * @param array $where Условия по контракту select().
     * @param string $afterWhere Доверенный SQL после WHERE, например ORDER BY/LIMIT.
     * @param array|null $single_Row Выходной аргумент: найденная строка; при отсутствии/неуникальности [].
     * @param bool $logging Журналировать метаданные операции без значений параметров.
     * @param bool $debug Дополнительно журналировать SQL без bindings; литералы в SQL не маскируются.
     * @throws \UnexpectedValueException Выбрано более одной строки.
     * @throws \PDOException Ошибка подключения или SQL.
     * @see self::select()
     */
    public static function selectExactlyOne(
        string $columns,
        array $table,
        string $beforeWhere,
        array $where,
        string $afterWhere,
        &$single_Row,
        bool $logging = false,
        bool $debug = false
    ): bool {
        $single_Row = [];
        if (!self::select($columns, $table, $beforeWhere, $where, $afterWhere, $rows, $logging, $debug)) {
            return false;
        }
        $count = count($rows);
        if ($count > 1) {
            throw new \UnexpectedValueException('Expected exactly one database row, got ' . $count);
        }
        if ($count === 0) {
            return false;
        }
        $single_Row = $rows[0];
        return true;
    }

    /**
     * Считает строки результата SELECT через обёртку SELECT COUNT(*) FROM (...).
     *
     * GROUP BY, DISTINCT и LIMIT влияют на считаемый результат. SQL-структура доверенная.
     * @param array<string> $table Таблицы и необязательные псевдонимы.
     * @param array $where Условия по контракту select().
     * @param bool $logging Журналировать метаданные без bindings.
     * @param bool $debug Журналировать SQL; встроенные литералы могут содержать секреты.
     * @throws \PDOException Ошибка выполнения или несовместимая с СУБД структура подзапроса.
     * @see self::select()
     */
    public static function selectCount(
        string $columns,
        array $table,
        string $beforeWhere,
        array $where,
        string $afterWhere,
        bool $logging = false,
        bool $debug = false
    ): int {
        $args = [];
        $query = self::buildSelect($columns, $table, $beforeWhere, $where, $afterWhere, $args);
        $sql = 'SELECT COUNT(*) AS row_count FROM (' . $query . ') AS counted_rows';
        if ($debug) {
            self::logQuery('debug', $sql, $args, true);
        }
        if ($logging) {
            self::logQuery('select_count', $sql, $args);
        }
        $rows = self::universal($sql, $args, true);
        return (int) $rows[0]['row_count'];
    }

    /**
     * Выполняет SELECT и сохраняет все строки в выходной аргумент rows.
     *
     * Пустая выборка тоже успешна: возвращает true и rows=[]. Ошибки не превращаются в false.
     * Имена таблиц/полей, операторы и SQL-фрагменты не экранируются: только доверенный код.
     * where: ['id'=>1], ['age'=>[18, '>=']], ['deleted_at'=>null] или RawCondition с bindings.
     * Для null допустимы только IS NULL/IS NOT NULL; для выражений используйте RawCondition.
     * @param array<string> $tables Таблицы с необязательными псевдонимами.
     * @param string $beforeWhere SQL до WHERE, например JOIN.
     * @param array $where Условия; пустой массив не ограничивает выборку.
     * @param string $afterWhere SQL после WHERE: GROUP BY, ORDER BY, LIMIT и т.п.
     * @param array|null $rows Выходной аргумент: массив ассоциативных строк.
     * @param bool $logging Журналировать метаданные операции без значений параметров.
     * @param bool $debug Журналировать SQL без bindings; SQL-литералы не маскируются.
     * @throws \PDOException Ошибка подключения или запроса.
     * @throws \InvalidArgumentException Неподдерживаемое значение параметра или условие NULL.
     */
    public static function select(
        string $columns,
        array $tables,
        string $beforeWhere,
        array $where,
        string $afterWhere,
        &$rows,
        bool $logging = false,
        bool $debug = false
    ): bool {
        $args = [];
        $sql = self::buildSelect($columns, $tables, $beforeWhere, $where, $afterWhere, $args);
        if ($debug) {
            self::logQuery('debug', $sql, $args, true);
        }
        try {
            if ($logging) {
                self::logQuery('select', $sql, $args);
            }
            $rows = self::universal($sql, $args, true);
            return true;
        } catch (Exception $e) {
            self::logFailure('select', $e);
            throw $e;
        }
    }

    /** Build the same SELECT for retrieval and counting, including bound conditions. */
    private static function buildSelect(
        string $columns,
        array $tables,
        string $beforeWhere,
        array $where,
        string $afterWhere,
        array &$args
    ): string {
        $condition = self::prepare_Where($where, $args);
        $tables = array_map(fn($table) => self::DB_PREFIX . $table, $tables);
        return 'SELECT ' . $columns . ' FROM ' . implode(',', $tables)
            . ' ' . $beforeWhere . ' ' . $condition . ' ' . $afterWhere;
    }

    /**
     * Добавляет строку и записывает последний автоинкрементный ID в выходной аргумент.
     *
     * Таблица и ключи set должны быть доверенными; значения передаются через ?.
     * Успех не означает commit активной транзакции.
     * @param array<string, scalar|null> $set Колонки и значения.
     * @param int|null $id Выходной идентификатор; контракт ограничен PHP int.
     * @param bool $logging Журналировать метаданные без bindings.
     * @param bool $debug Журналировать SQL, включая незамаскированные SQL-литералы.
     * @return bool true при выполнении; ошибки выбрасываются.
     * @throws \PDOException Ошибка SQL/подключения.
     * @throws \InvalidArgumentException Неподдерживаемое значение параметра.
     */
    public static function insert(
        string $table,
        array $set,
        int|null &$id = null,
        bool $logging = true,
        bool $debug = false
    ): bool {
        $args = [];
        $cols = '';
        $values = '';
        foreach ($set as $key => $value) {
            $cols .= ',' . $key;
            $values .= ',?';
            array_push($args, $value);
        }

        $sql = "INSERT INTO `" . self::DB_PREFIX . $table . "` (" . substr($cols, 1) . ") VALUES (" . substr($values, 1) . ")";

        if ($debug) {
            self::logQuery('debug', $sql, $args, true);
        }

        try {
            $id = self::universal($sql, $args, false, true);
            if ($logging) {
                self::logQuery('insert', $sql, $args);
            }
            return true;
        } catch (Exception $e) {
            self::logFailure('insert', $e);
            throw $e;
        }
    }

    /**
     * Удаляет строки по условиям, не возвращая число затронутых записей.
     *
     * Важно: пустой where удаляет ВСЕ строки таблицы; дополнительного подтверждения нет.
     * Имена и SQL-структура должны быть доверенными. Операция не завершает транзакцию.
     * @param array $where Условия по контракту select().
     * @param bool $logging Журналировать метаданные без значений параметров.
     * @param bool $debug Журналировать SQL без маскирования встроенных литералов.
     * @return bool true даже при отсутствии совпадений.
     * @throws \PDOException Ошибка выполнения SQL.
     * @see self::select()
     */
    public static function delete(string $table, array $where, bool $logging = true, bool $debug = false): bool
    {
        $args = array();

        $where = self::prepare_Where($where, $args);

        $sql = "DELETE FROM `" . self::DB_PREFIX . $table . "` " . $where;

        if ($debug) {
            self::logQuery('debug', $sql, $args, true);
        }
        try {
            self::universal($sql, $args, false);
            if ($logging) {
                self::logQuery('delete', $sql, $args);
            }
            return true;
        } catch (Exception $e) {
            self::logFailure('delete', $e);
            throw $e;
        }
    }

    /**
     * Обновляет строки по условиям, не возвращая число затронутых записей.
     *
     * Важно: пустой where обновляет ВСЕ строки. Имена таблицы/колонок и операторы
     * должны быть доверенными; значения связываются отдельно. Commit не выполняется.
     * @param array<string, scalar|null> $Set Колонки и новые значения.
     * @param array $where Условия по контракту select().
     * @param bool $logging Журналировать метаданные без bindings.
     * @param bool $debug Журналировать SQL; встроенные литералы не маскируются.
     * @return bool true даже при отсутствии изменений/совпадений.
     * @throws \PDOException Ошибка SQL.
     * @see self::select()
     */
    public static function update(string $table, array $Set, array $where, $logging = true, $debug = false)
    {
        $set = '';

        $args = array();
        foreach ($Set as $key => $value) {
            // if (isset($value)) {
            array_push($args, $value);
            $set .= ',' . $key . ' = ?';
            // } else {
            //     echo '12';
            // }
        }
        $where = self::prepare_Where($where, $args);

        $sql = "UPDATE `" . self::DB_PREFIX . $table . "` SET " . substr($set, 1) . $where;
        if ($debug) {
            self::logQuery('debug', $sql, $args, true);
        }
        try {
            self::universal($sql, $args, false);
            if ($logging) {
                self::logQuery('update', $sql, $args);
            }
            return true;
        } catch (Exception $e) {
            self::logFailure('update', $e);
            throw $e;
        }
    }


    private static function logQuery(string $operation, string $sql, array $args, bool $debug = false): void
    {
        $context = ['operation' => $operation, 'parameter_count' => count($args)];
        if ($debug) $context['sql'] = $sql;
        self::writeLog('Database query', $context, $debug ? 'debug/' : 'queries/');
    }

    private static function logFailure(string $operation, Exception $error): void
    {
        $context = ['operation' => $operation]
            + (DatabaseErrorContext::from($error) ?? ['exception_type' => $error::class]);
        self::writeLog('Database operation failed', $context, 'errors/', true);
    }

    private static function writeLog(string $message, array $context, string $folder, bool $error = false): void
    {
        try {
            if ($error) LogHelper::error($message, $context);
            else LogHelper::log($message, $context, $folder);
        } catch (\Throwable) {
            // Logging failure must not turn a completed write into an apparent database failure.
            error_log($message . ': ' . json_encode($context, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        }
    }

    /** One connection per ordinary PHP request; no persistent connections or automatic retries. */
    private static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }
        $dsn = "mysql:host=" . Settings::getString('db', 'server') . ";dbname=" . Settings::getString('db', 'name') . ";charset=utf8mb4";
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        self::$connection = new PDO($dsn, Settings::getString('db', 'user'), Settings::getString('db', 'password'), $opt);
        return self::$connection;
    }

    /**
     * Начинает транзакцию на общем ленивом PDO-соединении запроса.
     *
     * Вложенные транзакции/savepoint автоматически не поддерживаются.
     * @throws \PDOException Ошибка подключения или уже начатая транзакция.
     */
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    /**
     * Фиксирует активную транзакцию общего PDO-соединения.
     *
     * Автоматических повторов нет.
     * @throws \PDOException Транзакция не начата или фиксация завершилась ошибкой.
     */
    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    /**
     * Откатывает активную транзакцию общего PDO-соединения.
     *
     * Откат автоматически при прикладной ошибке не вызывается этим методом.
     * @throws \PDOException Транзакция не начата или откат завершился ошибкой.
     */
    public static function rollBack(): bool
    {
        return self::getConnection()->rollBack();
    }

    /**
     * Проверяет активную транзакцию, не создавая соединение при его отсутствии.
     */
    public static function inTransaction(): bool
    {
        return self::$connection !== null && self::$connection->inTransaction();
    }

    /**
     * Выполняет доверенный SQL через общее PDO-соединение.
     *
     * Связывает параметры по порядку обхода args, независимо от ключей: только позиционные ?.
     * Без параметров использует PDO::query(). Float передаёт как строку, остальные скалярные
     * типы и null связывает явно. Не выполняет commit и не возвращает affected rows.
     * @param string $sql Доверенный SQL; идентификаторы и структуру нельзя брать из запроса пользователя.
     * @param array<scalar|null>|null $args Значения позиционных плейсхолдеров.
     * @param bool|null $fetch Загрузить все строки через fetchAll().
     * @param bool|null $last_id Вернуть lastInsertId(); имеет приоритет над fetch.
     * @param bool $exec_time Устаревший флаг: измерение не публикуется и не возвращается.
     * @return array|string|false Строки при fetch, ID при last_id, иначе []; false возможен от драйвера lastInsertId().
     * @throws \PDOException Ошибка подключения или SQL.
     * @throws \InvalidArgumentException Параметр не скаляр и не null.
     */
    public static function universal($sql, $args = null, $fetch = null, $last_id = null, $exec_time = false)
    {
        $rows = [];

        $pdo = self::getConnection();
        if ((isset($args)) && (count($args) == 0)) {
            $args = null;
        }
        if ($args) {
            $prev_time = microtime(true);

            $stmt = $pdo->prepare($sql);
            $i = 0;
            foreach ($args as $key => $value) {
                $i++;
                $type = match (true) {
                    $value === null => PDO::PARAM_NULL,
                    is_bool($value) => PDO::PARAM_BOOL,
                    is_int($value) => PDO::PARAM_INT,
                    is_string($value), is_float($value) => PDO::PARAM_STR,
                    default => throw new \InvalidArgumentException('SQL parameters must be scalar values or null'),
                };
                $stmt->bindValue($i, $value, $type);
            }
            $stmt->execute();

            $difference = microtime(true) - $prev_time;

            // QueryTimer::stat_Query($sql, $difference);

            if ($exec_time) {
                // SEND_LOG(round($difference, 3), 1, true);
            }
        } else {
            $stmt = $pdo->query($sql);
        }

        if ($fetch) {
            $rows = $stmt->fetchAll();
        }
        if ($last_id) {
            return $pdo->lastInsertID();
        }

        if ($fetch) {
            return $rows;
        }
        return [];
    }

    private static function prepare_Where(array $where, &$args, $static_Where = '')
    {
        $where_string = ' WHERE';
        foreach ($where as $key => $value) {
            if ($value instanceof RawCondition) {
                $where_string .= ' (' . $value->sql . ') AND';
                array_push($args, ...$value->bindings);
                continue;
            }
            $key = explode('#', (string) $key, 2)[0];
            if ($value === null || (is_array($value) && array_key_exists(0, $value) && $value[0] === null)) {
                // Expressions must be explicit RawCondition objects, not disguised column names.
                if (!preg_match('/\A(?:`[^`]+`|[a-zA-Z_][a-zA-Z0-9_]*)(?:\.(?:`[^`]+`|[a-zA-Z_][a-zA-Z0-9_]*))*\z/', $key)) {
                    throw new \InvalidArgumentException('NULL conditions require a column name; use RawCondition for SQL expressions');
                }
                $operator = $value === null ? 'IS NULL' : strtoupper(trim((string) ($value[1] ?? '')));
                if (!in_array($operator, ['IS NULL', 'IS NOT NULL'], true)) {
                    throw new \InvalidArgumentException('NULL requires IS NULL or IS NOT NULL');
                }
                $where_string .= ' (' . $key . ' ' . $operator . ') AND';
                continue;
            }
            if (is_array($value)) {

                if (is_array($value[1])) {
                    $where_string .= ' ' . $value[1][2] . '(' . $key . ' ' . $value[1][0] . ' ?)' . $value[1][3] . ' ' . $value[1][1];
                } else {
                    $where_string .= ' (' . $key . ' ' . $value[1] . ' ?) AND';
                }
                array_push($args, $value[0]);
            } else {
                $where_string .= ' (' . $key . ' = ?) AND';
                array_push($args, $value);
            }
        }
        if (count($where) > 0) {
            if ($static_Where != '') {
                $where_string .= $static_Where;
            }

            $where_string = substr($where_string, 0, strrpos($where_string, ')') - strlen($where_string) + 1);
        } else {
            if ($static_Where != '') {
                $where_string = ' WHERE ' . $static_Where;
                $where_string = substr($where_string, 0, strrpos($where_string, ')') - strlen($where_string) + 1);
            } else {
                $where_string = ' ';
            }
        }
        return $where_string;
    }
}
