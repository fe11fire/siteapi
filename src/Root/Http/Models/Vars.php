<?php

namespace SiteApi\Root\Http\Models;

use stdClass;
use SiteApi\Root\Helpers\LogHelper;

class Vars
{
    static private $vars = [];


    /**
     * Сохраняет общее значение по имени, заменяя предыдущее.
     *
     * Хранилище статическое; автоматической очистки при повторном App::run() нет.
     */
    public static function set(string $name, mixed $value)
    {
        self::$vars[$name] = $value;
    }

    /**
     * Добавляет элемент в массив общего хранилища, создавая массив при отсутствии/null.
     *
     * Существующее значение другого типа не преобразуется и приведёт к TypeError.
     */
    public static function array_push(string $name, mixed $value)
    {
        if (!isset(self::$vars[$name])) {
            self::$vars[$name] = [];
        }
        array_push(self::$vars[$name], $value);
    }

    /**
     * Устанавливает свойство объекта в хранилище; при отсутствии/null создаёт stdClass.
     *
     * Существующее скалярное значение не преобразуется; тип контейнера проверяет вызывающий код.
     */
    public static function obj_push(string $name, string $subname, mixed $value)
    {
        if (!isset(self::$vars[$name])) {
            self::$vars[$name] = new stdClass();
        }
        self::$vars[$name]->{$subname} = $value;
    }

    /**
     * Возвращает общее значение или элемент массива/свойство объекта.
     *
     * Отсутствие и null трактуются как отсутствие: возвращается false и пишется журнал.
     * Параметр index=null означает чтение всего значения.
     */
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

    /**
     * Проверяет наличие ненулевого значения или элемента общего хранилища.
     *
     * Использует isset(): сохранённый null считается отсутствующим.
     * @return bool
     */
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

    /**
     * Выводит script с объявлением var и безопасно закодированными JSON-данными.
     *
     * Допускает только простое ASCII-имя, без зарезервированных слов и путей свойств.
     * Экранирует HTML-опасные символы JSON; заголовки и CSP не устанавливает.
     * @throws \InvalidArgumentException Недопустимое имя JS-переменной.
     * @throws \JsonException Данные невозможно закодировать; частичный script не выводится.
     */
    public static function make_JS_var(string $JS_name_variable, $data)
    {
        // Restrict the API to portable ASCII identifiers, not expressions or property paths.
        $reserved = [
            'await', 'break', 'case', 'catch', 'class', 'const', 'continue', 'debugger',
            'default', 'delete', 'do', 'else', 'enum', 'export', 'extends', 'false',
            'finally', 'for', 'function', 'if', 'implements', 'import', 'in',
            'instanceof', 'interface', 'let', 'new', 'null', 'package', 'private',
            'protected', 'public', 'return', 'static', 'super', 'switch', 'this',
            'throw', 'true', 'try', 'typeof', 'var', 'void', 'while', 'with', 'yield',
            'eval', 'arguments',
        ];
        if (!preg_match('/\A[a-zA-Z_$][a-zA-Z0-9_$]*\z/', $JS_name_variable)
            || in_array($JS_name_variable, $reserved, true)) {
            throw new \InvalidArgumentException('Invalid JavaScript variable name');
        }
        echo '<script>var ' . $JS_name_variable . ' = ' . json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ';</script>';
    }

    /**
     * Выводит script с console.log() для JSON-совместимого значения.
     *
     * Данные становятся доступны посетителю; секреты передавать нельзя.
     * @throws \JsonException Ошибка кодирования до вывода script.
     */
    public static function console_log($data)
    {
        echo '<script>console.log(' . json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ');</script>';
    }
}
