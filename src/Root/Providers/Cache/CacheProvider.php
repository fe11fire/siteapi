<?php

namespace SiteApi\Root\Providers\Cache;

use SiteApi\Root\Providers\Cache\CacheEnum;
use SiteApi\Root\Providers\Cache\CacheContract;

class CacheProvider implements CacheContract
{
    const PERIOD_DEFAULT = 299;

    private static CacheContract $instance;

    public static function set(string $key, mixed $value, int $period = CacheProvider::PERIOD_DEFAULT): void
    {
        self::initDefault();
        self::$instance::set($key, $value, $period);
    }

    public static function get(string $key): string | array | false
    {
        self::initDefault();
        return self::$instance::get($key);
    }

    public static function delete(string $key): void
    {
        self::initDefault();
        self::$instance::delete($key);
    }

    public static function init(string $name)
    {
        self::$instance = CacheEnum::tryFrom($name)->getProvider();
    }

    public static function initDefault()
    {
        if (!isset(self::$instance)) {
            self::$instance = CacheEnum::MEMCACHE->getProvider();
        }
    }

    public static function info(): string
    {
        if (!isset(self::$instance)) {
            return 'Null Cache';
        }
        return self::$instance::info();
    }

    public static function status(): bool
    {
        if (!isset(self::$instance)) {
            return false;
        }
        return self::$instance::status();
    }
}
