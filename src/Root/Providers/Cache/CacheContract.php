<?php

namespace SiteApi\Root\Providers\Cache;

use SiteApi\Root\Providers\Cache\CacheProvider;

interface CacheContract
{
    /**
     * @param string    $key     The key name to set.
     * @param mixed     $value   The value to set the key to.
     * @param int       $period  Expire time in seconds
     */
    public static function set(string $key, mixed $value, int $period = CacheProvider::PERIOD_DEFAULT): void;
    public static function get(string $key): string | array | false;
    public static function delete(string $key): void;
}
