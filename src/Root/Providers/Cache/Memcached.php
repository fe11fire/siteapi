<?php

namespace SiteApi\Root\Providers\Cache;

use SiteApi\Root\Helpers\LogHelper;
use SiteApi\Root\Providers\Cache\CacheProvider;
use Memcached as GlobalMemcached;

class Memcached implements CacheContract
{

    private static GlobalMemcached | null $instance = null;

    private static function init(): bool
    {
        if (self::$instance === null) {
            self::$instance = new GlobalMemcached;
            self::$instance->addServer(CacheEnum::MEMCACHED->getHost(), CacheEnum::MEMCACHED->getPort());
            if (!self::$instance->getVersion()) {
                LogHelper::error('Memcached not connected');
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
        self::$instance->set($key, $value, $period);
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

    public static function info(): string
    {
        return CacheEnum::MEMCACHED->value;
    }

    public static function status(): bool
    {
        return self::$instance !== null;
    }
}
