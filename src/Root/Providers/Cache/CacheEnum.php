<?php

namespace SiteApi\Root\Providers\Cache;

use SiteApi\Root\Providers\Cache\Redis;
use SiteApi\Root\Settings\Settings;
use SiteApi\Root\Providers\Cache\Memcache;
use SiteApi\Root\Providers\Cache\Memcached;
use SiteApi\Root\Providers\Cache\CacheContract;

enum CacheEnum: string
{
    case MEMCACHE = 'Memcache';
    case REDIS = 'Redis';
    case MEMCACHED = 'Memcached';

    public function getProvider(): CacheContract
    {
        return match ($this) {
            CacheEnum::MEMCACHE => new Memcache(),
            CacheEnum::REDIS => new Redis(),
            CacheEnum::MEMCACHED => new Memcached(),
        };
    }

    public function getHost(): string
    {
        return match ($this) {
            CacheEnum::MEMCACHE => Settings::get_Default('memcache', 'host', '127.0.0.1'),
            CacheEnum::REDIS => Settings::get_Default('redis', 'host', 'redis'),
            CacheEnum::MEMCACHED => Settings::get_Default('memcached', 'host', 'memcached'),
        };
    }
    public function getPort(): int
    {
        return match ($this) {
            CacheEnum::MEMCACHE => Settings::get_Default('memcache', 'port', 11211),
            CacheEnum::REDIS => Settings::get_Default('redis', 'port', 6379),
            CacheEnum::MEMCACHED => Settings::get_Default('memcached', 'port', 11211),
        };
    }
}
