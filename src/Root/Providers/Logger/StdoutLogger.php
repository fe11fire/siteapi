<?php

namespace SiteApi\Root\Providers\Logger;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class StdoutLogger implements LoggerContract
{
    public static function get(string $folder, Level $status): Logger
    {
        $log = new Logger('name');
        $log->pushHandler(new StreamHandler("php://stdout", $status));
        return $log;
    }

    public static function info(): string
    {
        return LoggerEnum::STDOUT->value;
    }
}
