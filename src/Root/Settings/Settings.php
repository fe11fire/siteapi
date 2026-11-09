<?php

namespace SiteApi\Root\Settings;

use stdClass;
use Exception;

use SiteApi\Root\Helpers\LogHelper;
use SiteApi\Root\Helpers\FileHelper;
use SiteApi\Root\Helpers\StringHelper;

class Settings
{
    public static string $directory = 'settings/main';
    private static array $settings_files;
    private static stdClass $settings;
    private static stdClass $default_directories;

    /**
     * Загружает JSON-объекты из Settings::$directory в статическое хранилище.
     *
     * Сбрасывает предыдущие настройки; при ошибке набор не публикуется частично.
     * @param bool $allowMissing Разрешить отсутствие каталога, но не ошибочные файлы.
     * @throws \RuntimeException Каталог/файл недоступен или JSON некорректен.
     * @throws \UnexpectedValueException Корень JSON не является объектом.
     */
    public static function init(bool $allowMissing = false)
    {
        self::$settings = new stdClass();
        self::$default_directories = (object) array(
            'logs' => 'siteapilogs',
            'middlewares' => 'scripts/middlewares',
            'routes' => 'routes',
            'headers' => 'common/headers',
        );

        self::$settings_files = $allowMissing && !file_exists(self::$directory)
            ? [] : FileHelper::list_Files(self::$directory);

        // Publish settings only after every JSON file has passed validation.
        $loaded = new stdClass();
        foreach (self::$settings_files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'json') {
                continue;
            }
            $path = self::$directory . '/' . $file;
            $contents = @file_get_contents($path);
            if ($contents === false) {
                throw new \RuntimeException('Cannot read settings file: ' . $path);
            }
            try {
                $data = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $error) {
                throw new \RuntimeException('Invalid JSON in settings file: ' . $path . ' (' . $error->getMessage() . ')', 0, $error);
            }
            if (!$data instanceof stdClass) {
                throw new \UnexpectedValueException('Settings file must contain a JSON object: ' . $path);
            }
            $loaded->{substr($file, 0, -5)} = $data;
        }
        self::$settings = $loaded;
    }

    private static function lookup(string $file, string $name, bool &$found): mixed
    {
        $env = StringHelper::up_Letters($file) . '_' . StringHelper::up_Letters($name);
        $value = getenv($env);
        if ($value !== false) {
            $found = true;
            return $value;
        }
        $section = self::$settings->{$file} ?? null;
        $found = $section instanceof stdClass && property_exists($section, $name);
        return $found ? $section->{$name} : null;
    }

    /**
     * Читает обязательную настройку: окружение FILE_NAME имеет приоритет над JSON.
     *
     * Окружение возвращает строку; типы JSON сохраняются, включая явный null.
     * @throws \RuntimeException Настройка отсутствует.
     */
    public static function get(string $file, string $name): mixed
    {
        $found = false;
        $value = self::lookup($file, $name, $found);
        if (!$found) throw new \RuntimeException('Missing setting: ' . $file . '.' . $name);
        return $value;
    }

    /**
     * Читает настройку с приоритетом окружения, иначе JSON, иначе default.
     *
     * Default применяется только при отсутствии; явный null и пустая строка сохраняются.
     */
    public static function get_Default(string $file, string $name, mixed $default): mixed
    {
        $found = false;
        $value = self::lookup($file, $name, $found);
        return $found ? $value : $default;
    }

    private static function typed(string $file, string $name, string $type, bool $hasDefault, mixed $default): mixed
    {
        $value = $hasDefault ? self::get_Default($file, $name, $default) : self::get($file, $name);
        if ($type === 'string' && is_string($value)) return $value;
        if ($type === 'bool') {
            if (in_array($value, [true, 1, '1', 'true'], true)) return true;
            if (in_array($value, [false, 0, '0', 'false'], true)) return false;
        }
        if ($type === 'int') {
            if (is_int($value)) return $value;
            if (is_string($value) && preg_match('/\A-?(?:0|[1-9][0-9]*)\z/', $value)) {
                $integer = filter_var($value, FILTER_VALIDATE_INT);
                if ($integer !== false) return $integer;
            }
        }
        if ($type === 'array') {
            if (is_array($value)) return $value;
            if (is_string($value)) {
                try {
                    $array = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
                    if (is_array($array)) return $array;
                } catch (\JsonException) {
                    // Do not include the value or parser exception in diagnostics.
                }
            }
        }
        throw new \UnexpectedValueException('Setting ' . $file . '.' . $name . ' must be ' . $type);
    }

    /**
     * Читает строго строковую настройку с необязательным default третьим аргументом.
     *
     * Отсутствующий default и явно переданный null различаются; null не является строкой.
     * @throws \RuntimeException Настройка отсутствует и default не передан.
     * @throws \UnexpectedValueException Значение, включая default, не строка.
     */
    public static function getString(string $file, string $name, mixed $default = null): string
    {
        return self::typed($file, $name, 'string', func_num_args() >= 3, $default);
    }

    /**
     * Читает bool: допускает true/false, 1/0 и строки 'true'/'false', '1'/'0'.
     *
     * Регистр и пробелы автоматически не нормализуются. Default учитывается, если передан.
     * @throws \RuntimeException Настройка отсутствует без default.
     * @throws \UnexpectedValueException Значение нельзя преобразовать по этим правилам.
     */
    public static function getBool(string $file, string $name, mixed $default = null): bool
    {
        return self::typed($file, $name, 'bool', func_num_args() >= 3, $default);
    }

    /**
     * Читает int либо десятичную строку в диапазоне PHP int.
     *
     * Строки с пробелами, знаком +, ведущими нулями или дробной частью не принимаются.
     * @throws \RuntimeException Настройка отсутствует без default.
     * @throws \UnexpectedValueException Недопустимый формат, тип или переполнение.
     */
    public static function getInt(string $file, string $name, mixed $default = null): int
    {
        return self::typed($file, $name, 'int', func_num_args() >= 3, $default);
    }

    /**
     * Читает PHP-массив либо строку с JSON-массивом.
     *
     * JSON-объект не преобразуется в массив; объекты внутри JSON-списков остаются stdClass.
     * Default проходит ту же проверку типа.
     * @throws \RuntimeException Настройка отсутствует без default.
     * @throws \UnexpectedValueException Значение не массив и не строка с JSON-массивом.
     */
    public static function getArray(string $file, string $name, mixed $default = null): array
    {
        return self::typed($file, $name, 'array', func_num_args() >= 3, $default);
    }

    /**
     * Возвращает путь из paths или встроенных стандартных каталогов.
     *
     * Ограничение: PATHS_* из окружения учитывается только при наличии ненулевого
     * соответствующего ключа в paths.json. Без него возвращается стандартный путь.
     * Путь не нормализуется и его существование не проверяется.
     * @param string $name Имя пути, например routes, logs, headers, middlewares.
     * @throws \Exception Имя отсутствует и в paths, и в стандартных каталогах.
     * @throws \UnexpectedValueException Объявленное значение пути не строка.
     */
    public static function get_Directory($name): string
    {
        if (
            (isset(self::$settings->{'paths'})) &&
            (isset(self::$settings->{'paths'}->{$name}))
        ) {
            return self::getString('paths', $name);
        }
        if (!isset(self::$default_directories->{$name})) {
            LogHelper::log('directory name not found', ['name' => $name], 'errors/');
            throw new Exception("directory name not found", 1);
        }
        return self::$default_directories->{$name};
    }

    /**
     * Возвращает объявленный путь paths либо переданный default.
     *
     * PATHS_* учитывается только при наличии ненулевого соответствующего ключа в JSON.
     * При отсутствии ключа окружение не проверяется. Путь не нормализуется.
     */
    public static function get_Directory_Default($name, string $default): string
    {
        if (
            (isset(self::$settings->{'paths'})) &&
            (isset(self::$settings->{'paths'}->{$name}))
        ) {
            return self::getString('paths', $name);
        }
        return $default;
    }
}
