<?php

namespace SiteApi\Root\Providers\Logger;

use Monolog\Level;
use Monolog\Logger;

class LoggerProvider implements LoggerContract
{
    public static function get(string $folder, Level $status): Logger
    {
        self::initDefault();
        return self::$instance::get($folder, $status);
    }

    private static LoggerContract $instance;

    public static function init(string $name)
    {
        self::$instance = LoggerEnum::tryFrom($name)->getProvider();
    }

    private static function initDefault()
    {
        if (!isset(self::$instance)) {
            self::$instance = LoggerEnum::FILE->getProvider();
        }
    }
}
