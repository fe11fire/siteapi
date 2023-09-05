<?php

namespace SiteApi\Root\Settings;

use Exception;
use SiteApi\Root\Helpers\FileHelper;
use SiteApi\Root\Helpers\LogHelper;
use stdClass;

class Settings
{
    public static string $directory = 'settings/main';
    private static array $settings_files;
    private static stdClass $settings;
    private static stdClass $default_directories;

    public static function init()
    {
        self::$settings = new stdClass();
        self::$default_directories = (object) array(
            'logs' => 'settings/main',
            'middlewares' => 'scripts/middlewares',
            'routes' => 'routes',
        );

        self::$settings_files = FileHelper::list_Files(self::$directory);
        foreach (self::$settings_files as $key_ => $file) {
            self::$settings->{$file} = new stdClass();
            $str = json_decode(file_get_contents(self::$directory . '/' . $file));
            foreach ($str as $key => $value) {
                self::$settings->{$file}->{$key} = $value;
            }
        }
    }

    public static function get(string $file, string $name): string
    {
        if (!isset(self::$settings->{$file})) {
            LogHelper::log('file not found', ['file' => $file, 'name' => $name], 'errors/');
            throw new Exception("file not found", 1);
        }
        if (!isset(self::$settings->{$file}->{$name})) {
            LogHelper::log('name in file not found', ['file' => $file, 'name' => $name], 'errors/');
            throw new Exception("name in file not found", 1);
        }
        return self::$settings->{$file}->{$name};
    }

    public static function get_Directory($name): string
    {
        if (!isset(self::$default_directories->{$name})) {
            LogHelper::log('directory name not found', ['name' => $name], 'errors/');
            throw new Exception("directory name not found", 1);
        }
        return self::$default_directories->{$name};
    }
}
