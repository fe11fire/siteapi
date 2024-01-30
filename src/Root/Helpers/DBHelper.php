<?php

namespace SiteApi\Root\Helpers;


use PDO;
use Exception;
use SiteApi\Root\Helpers\LogHelper;
use SiteApi\Root\Settings\Settings;

class DBHelper
{
    const DB_PREFIX = '';

    public static function selectSingle(
        string $columns,
        string $table,
        array $where,
        string $postfix,
        /** GROUP ORDER HAVING in text */
        &$single_Row,
        bool $logging = false,
        bool $debug = false
    ) {
        $single_Row = [];
        if (
            (self::select($columns, $table, $where, $postfix, $rows, $logging, $debug)) &&
            (count($rows) == 1)
        ) {
            $single_Row = $rows[0];
            return true;
        }
        return false;
    }

    public static function selectCount(
        string $columns,
        string $table,
        array $where,
        string $postfix,
        /** GROUP ORDER HAVING in text */
        bool $logging = false,
        bool $debug = false
    ): int {
        if (
            self::select($columns, $table, $where, $postfix, $rows, $logging, $debug)
        ) {
            return count($rows);
        }
        return 0;
    }

    public static function select(
        string $columns,
        string $table,
        array $where,
        string $postfix,
        /** GROUP ORDER HAVING in text */
        &$rows,
        bool $logging = false,
        bool $debug = false
    ): bool {
        $args = [];
        $where = self::prepare_Where($where, $args);

        $sql = "SELECT " . $columns . " FROM " . self::DB_PREFIX . $table . " " . $where . ' ' . $postfix;
        if ($debug) {
            LogHelper::log('Select Debug', ['sql' => $sql, 'args' => $args]);
        }
        try {
            if ($logging) {
                LogHelper::log('Select Logging', [$where]);
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
        // if (!issSettings::get('db', 'server'))) {
        //     LogHelper::log('Queries', ['error' => 'DB data not init'], 'errors/');
        //     throw new Exception("DB Data not Init", 1);
        // }

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
