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

    public static function init(LoggerEnum|string $logger): void
    {
        $provider = $logger instanceof LoggerEnum ? $logger : LoggerEnum::tryFrom($logger);
        if ($provider === null) {
            $allowed = implode(', ', array_map(fn(LoggerEnum $case) => $case->value, LoggerEnum::cases()));
            throw new \InvalidArgumentException('Unknown logger "' . $logger . '". Allowed values: ' . $allowed);
        }
        self::$instance = $provider->getProvider();
    }

    private static function initDefault()
    {
        if (!isset(self::$instance)) {
            self::$instance = LoggerEnum::FILE->getProvider();
        }
    }

    public static function info(): string
    {
        self::initDefault();
        return self::$instance::info();
    }
}
