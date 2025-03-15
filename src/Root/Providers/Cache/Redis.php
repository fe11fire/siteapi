<?php

namespace SiteApi\Root\Providers\Cache;

use Exception;
use Redis as GlobalRedis;
use SiteApi\Root\Helpers\LogHelper;
use SiteApi\Root\Providers\Cache\CacheProvider;

class Redis implements CacheContract
{
    private static GlobalRedis | null $instance = null;

    private static function init(): bool
    {
        if (self::$instance === null) {
            self::$instance = new GlobalRedis([
                'host' => CacheEnum::REDIS->getHost(),
                'port' => CacheEnum::REDIS->getPort(),
            ]);
            try {
                self::$instance->ping();
                self::$instance->pconnect(CacheEnum::REDIS->getHost(), CacheEnum::REDIS->getPort());
            } catch (Exception $e) {
                self::$instance = null;
                LogHelper::error('Redis not connected');
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
        self::$instance->del($key);
    }

    public static function info(): string
    {
        return CacheEnum::REDIS->value;
    }

    public static function status(): bool
    {
        return self::$instance !== null;
    }
}
