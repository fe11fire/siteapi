<?php

namespace SiteApi\Root\Http\Models;

use stdClass;
use SiteApi\Root\Helpers\LogHelper;

class Vars
{
    static private $vars = [];


    public static function set(string $name, mixed $value)
    {
        self::$vars[$name] = $value;
    }

    public static function array_push(string $name, mixed $value)
    {
        if (!isset(self::$vars[$name])) {
            self::$vars[$name] = [];
        }
        array_push(self::$vars[$name], $value);
    }

    public static function obj_push(string $name, string $subname, mixed $value)
    {
        if (!isset(self::$vars[$name])) {
            self::$vars[$name] = new stdClass();
        }
        self::$vars[$name]->{$subname} = $value;
    }

    public static function get(string $name, mixed $index = null): mixed
    {
        if (!isset(self::$vars[$name])) {
            LogHelper::log('No name `' . $name . '` in Vars', [], 'errors/');
            return false;
        }

        if (isset($index)) {
            switch (gettype(self::$vars[$name])) {
                case 'array':
                    if (!isset(self::$vars[$name][$index])) {
                        LogHelper::log('No index `' . $index . '` in Array name `' . $name . '` in Vars', [], 'errors/');
                        return false;
                    }
                    return self::$vars[$name][$index];
                    break;
                case 'object':
                    if (!isset(self::$vars[$name]->{$index})) {
                        LogHelper::log('No index `' . $index . '` in Object name `' . $name . '` in Vars', [], 'errors/');
                        return false;
                    }
                    return self::$vars[$name]->{$index};
                    break;

                default:
                    LogHelper::log('No index `' . $index . '` in ' . gettype(self::$vars[$name]) . ' name `' . $name . '` in Vars', [], 'errors/');
                    return false;
                    break;
            }
        }

        return self::$vars[$name];
    }

    public static function isset(string $name, mixed $index = null): mixed
    {
        if (!isset(self::$vars[$name])) {
            return false;
        }

        if (!is_null($index)) {
            switch (gettype(self::$vars[$name])) {
                case 'array':
                    if (!isset(self::$vars[$name][$index])) {
                        return false;
                    }
                    break;
                case 'object':
                    if (!isset(self::$vars[$name]->{$index})) {
                        return false;
                    }
                    break;

                default:
                    return false;
                    break;
            }
        }

        return true;
    }

    public static function make_JS_var(string $JS_name_variable, $data)
    {
        echo '<script>var ' . $JS_name_variable . ' = ' . json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';</script>';
    }

    public static function console_log($data)
    {
        echo '<script>console.log(' . json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ');</script>';
    }
}
