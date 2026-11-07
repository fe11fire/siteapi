<?php
namespace SiteApi\Root\Providers\Cache;

enum CacheEnum: string
{
    case REDIS = 'Redis';
    case MEMCACHED = 'Memcached';

    public function getProvider(): CacheContract
    {
        return match ($this) {
            self::REDIS => new Redis(),
            self::MEMCACHED => new Memcached(),
        };
    }
}
