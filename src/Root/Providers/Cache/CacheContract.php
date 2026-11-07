<?php
namespace SiteApi\Root\Providers\Cache;

/** Adapters transport encoded strings; null means the key is absent. */
interface CacheContract
{
    /** Period is seconds; zero means no expiration. Failures throw. */
    public function set(string $key, string $value, int $period): void;
    public function get(string $key): ?string;
    public function delete(string $key): void;
    public function info(): string;
    public function status(): bool;
}
