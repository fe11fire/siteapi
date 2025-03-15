<?php

namespace SiteApi\Root\Providers\Cache;

use Memcache as GlobalMemcache;
use SiteApi\Root\Helpers\LogHelper;
use SiteApi\Root\Providers\Cache\CacheProvider;

class Memcache implements CacheContract
{

    private static GlobalMemcache | null $instance = null;

    private static function init(): bool
    {
        if (self::$instance === null) {
            self::$instance = new GlobalMemcache;
            if (!self::$instance->pconnect(CacheEnum::MEMCACHE->getHost(), CacheEnum::MEMCACHE->getPort())) {
                LogHelper::error('Memcache not connected');
                self::$instance->close();
                self::$instance = null;
                return false;
            }
        }

        return true;
    }

    public static function set(string $key, mixed $value, int $period = CacheProvider::PERIOD_DEFAULT): void
    {
        if (!self::init()) {
            return;
        }
        self::$instance->set($key, $value, false, $period);
    }

    public static function get(string $key): string | array | false
    {
        if (!self::init()) {
            return false;
        }
        $v = self::$instance->get($key);
        return $v;
    }

    public static function delete(string $key): void
    {
        if (!self::init()) {
            return;
        }
        self::$instance->delete($key);
    }
}
