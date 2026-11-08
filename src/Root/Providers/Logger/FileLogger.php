<?php

namespace SiteApi\Root\Providers\Logger;

use Carbon\Carbon;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use SiteApi\Root\Settings\Settings;
use SiteApi\Root\Helpers\FileHelper;

class FileLogger implements LoggerContract
{
    public static function get(string $folder, Level $status): Logger
    {
        $now = Carbon::now();
        $base = rtrim(Settings::get_Directory_Default('logs', 'siteapilogs'), '/');
        $folder = trim($folder, '/');
        $dir = $base . '/' . $now->format('Y') . ($folder !== '' ? '/' . $folder : '');
        FileHelper::make_Folder($dir);

        $log = new Logger('name');
        $log->pushHandler(new StreamHandler($dir . '/' . $now->format('m-d') . '.log', $status));
        return $log;
    }

    public static function info(): string
    {
        return LoggerEnum::FILE->value;
    }
}
