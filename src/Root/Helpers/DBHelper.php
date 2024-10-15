<?php

namespace SiteApi\Root\Helpers;


use PDO;
use Exception;
use SiteApi\Root\Helpers\LogHelper;
use SiteApi\Root\Settings\Settings;

class DBHelper
{
    const DB_PREFIX = '';

    /**
     * Select first row or return false
     *
     * @param string $columns '*' or list of columns
     * @param array $tables tables with shortNames
     * @param string $beforeWhere command before Where like JOIN
     * @param array $where where conclusions in key value format
     * @param string $beforeWhere commands after where like GROUP ORDER HAVING
     * @param array $single_Row first row from selected
     * @param bool $logging log where, args, sql in 'queries/'
     * @param bool $debug log where, args, sql in 'debug/'
     * @return bool
     */

    public static function selectSingle(
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
        if (
            (self::select($columns, $table, $beforeWhere, $where, $afterWhere, $rows, $logging, $debug)) &&
            (count($rows) == 1)
        ) {
            $single_Row = $rows[0];
            return true;
        }
        return false;
    }

    /**
     * Select count of rows
     *
     * @param string $columns '*' or list of columns
     * @param array $tables tables with shortNames
     * @param string $beforeWhere command before Where like JOIN
     * @param array $where where conclusions in key value format
     * @param string $beforeWhere commands after where like GROUP ORDER HAVING
     * @param bool $logging log where, args, sql in 'queries/'
     * @param bool $debug log where, args, sql in 'debug/'
     * @return int 
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
        if (
            self::select($columns, $table, $beforeWhere, $where, $afterWhere, $rows, $logging, $debug)
        ) {
            return count($rows);
        }
        return 0;
    }

    /**
     * Select rows
     *
     * @param string $columns '*' or list of columns
     * @param array $tables tables with shortNames
     * @param string $beforeWhere command before Where like JOIN
     * @param array $where where conclusions in key value format
     * @param string $beforeWhere commands after where like GROUP ORDER HAVING
     * @param $rows selected rows
     * @param bool $logging log where, args, sql in 'queries/'
     * @param bool $debug log where, args, sql in 'debug/'
     * @return bool //true or false
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
        $where = self::prepare_Where($where, $args);
        foreach ($tables as &$table) {
            $table = self::DB_PREFIX . $table;
        }
        $sql = "SELECT " . $columns . " FROM " . implode(',', $tables) . " " . $beforeWhere . " " . $where . ' ' . $afterWhere;
        if ($debug) {
            LogHelper::log('Select Debug', ['args' => $args, 'where' => $where, 'sql' => $sql], 'debug/');
        }
        try {
            if ($logging) {
                LogHelper::log('Select Logging', ['args' => $args, 'where' => $where, 'sql' => $sql], 'queries/');
            }
            $rows = self::universal($sql, $args, true);
            return true;
        } catch (Exception $e) {
            LogHelper::log('select', [$e->getMessage()], 'errors/');
            throw new Exception($e->getMessage());
            return false;
        }
    }

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
            LogHelper::log('Insert Debug', ['sql' => $sql, 'args' => $args]);
        }

        try {
            $id = self::universal($sql, $args, false, true);
            if ($logging) {
                LogHelper::log('insert', ['В таблицу ' . $table . ' добавлена запись ID = ' . $id]);
            }
            return true;
        } catch (Exception $e) {
            LogHelper::log('insert', [$e->getMessage()], 'errors/');
            throw new Exception($e->getMessage());
            return false;
        }
    }

    public static function delete(string $table, array $where, bool $logging = true, bool $debug = false): bool
    {
        $args = array();

        $where = self::prepare_Where($where, $args);

        $sql = "DELETE FROM `" . self::DB_PREFIX . $table . "` " . $where;

        if ($debug) {
            LogHelper::log('Delete Debug', ['sql' => $sql, 'args' => $args]);
        }
        try {
            self::universal($sql, $args, false);
            if ($logging) {
                LogHelper::log('delete', ['0' => 'В таблице ' . $table . ' удалена запись', 'Where' => $where]);
            }
            return true;
        } catch (Exception $e) {
            LogHelper::log('delete', [$e->getMessage()], 'errors/');
            throw new Exception($e->getMessage());
            return false;
        }
    }

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
            LogHelper::log('Update Debug', ['sql' => $sql, 'args' => $args]);
        }
        try {
            self::universal($sql, $args, false);
            if ($logging) {
                LogHelper::log('update', ['0' => 'В таблице ' . $table . ' обновлены записи', 'Set' => $Set, 'Where' => $where]);
            }
            return true;
        } catch (Exception $e) {
            LogHelper::log('update', [$e->getMessage()], 'errors/');
            throw new Exception($e->getMessage());
            return false;
            return false;
        }
    }


    public static function universal($sql, $args = null, $fetch = null, $last_id = null, $exec_time = false)
    {
        $rows = [];

        $dsn = "mysql:host=" . Settings::get('db', 'server') . ";dbname=" . Settings::get('db', 'name') . ";charset=utf8mb4";
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, Settings::get('db', 'user'), Settings::get('db', 'password'), $opt);
        if ((isset($args)) && (count($args) == 0)) {
            $args = null;
        }
        if ($args) {
            $prev_time = microtime(true);

            $stmt = $pdo->prepare($sql);
            $i = 0;
            foreach ($args as $key => $value) {
                $i++;
                if (is_null($value)) {
                    $stmt->bindValue($i, null);
                } else {
                    $stmt->bindValue($i, $value);
                }
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

        $pdo = null;
        $dsn = null;
        if ($fetch) {
            return $rows;
        }
        return [];
    }

    private static function prepare_Where(array $where, &$args, $static_Where = '')
    {
        $where_string = ' WHERE';
        foreach ($where as $key => $value) {
            if (isset($value)) {
                $hash_ind = strpos($key, '#');
                if ($hash_ind !== false) {
                    $key = substr($key, 0, $hash_ind);
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
            } else {
                $where_string .= ' (' . $key . ') AND';
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
