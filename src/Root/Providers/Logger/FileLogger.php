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
        $dir = Settings::get_Directory_Default('logs', 'siteapilogs') . '/' . Carbon::now()->format('Y') . '/' . $folder;
        if (!file_exists($dir) || !is_dir($dir)) {
            mkdir($dir, recursive: true);
        }

        $log = new Logger('name');
        $log->pushHandler(new StreamHandler($dir . Carbon::now()->format('m-d') . '.log', $status));
        return $log;
    }
}
