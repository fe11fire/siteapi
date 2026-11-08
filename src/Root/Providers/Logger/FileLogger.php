<?php

namespace SiteApi\Root\Providers\Logger;

use Carbon\Carbon;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use SiteApi\Root\Settings\Settings;

class FileLogger implements LoggerContract
{
    public static function get(string $folder, Level $status): Logger
    {
        $now = Carbon::now();
        $base = rtrim(Settings::get_Directory_Default('logs', 'siteapilogs'), '/');
        $folder = trim($folder, '/');
        $dir = $base . '/' . $now->format('Y') . ($folder !== '' ? '/' . $folder : '');
        if (!file_exists($dir) || !is_dir($dir)) {
            mkdir($dir, recursive: true);
        }

        $log = new Logger('name');
        $log->pushHandler(new StreamHandler($dir . '/' . $now->format('m-d') . '.log', $status));
        return $log;
    }

    public static function info(): string
    {
        return LoggerEnum::FILE->value;
    }
}
