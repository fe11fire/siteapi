<?php
namespace SiteApi\Root\Providers\Cache;

use InvalidArgumentException;
use UnexpectedValueException;

class CacheProvider
{
    public const PERIOD_DEFAULT = 299;
    private const FORMAT = "SiteApi:PHP:1\0";
    private static CacheContract $instance;
    private static string $prefix = 'siteapi';

    /** String names remain accepted for existing applications. */
    public static function init(CacheEnum|string $name, string $prefix = 'siteapi'): void
    {
        $type = $name instanceof CacheEnum ? $name : CacheEnum::tryFrom($name);
        if ($type === null) {
            throw new InvalidArgumentException('Unknown cache provider: ' . $name);
        }
        self::$instance = $type->getProvider();
        self::$prefix = $prefix;
    }

    public static function initDefault(): void
    {
        if (!isset(self::$instance)) {
            self::init(CacheEnum::MEMCACHED);
        }
    }

    private static function key(string $key): string
    {
        if ($key === '') {
            throw new InvalidArgumentException('Cache key must not be empty');
        }
        // Versioned namespace isolates legacy entries; hashing keeps keys portable.
        return 'siteapi:v1:' . hash('sha256', self::$prefix . "\0" . $key);
    }

    public static function set(string $key, mixed $value, int $period = self::PERIOD_DEFAULT): void
    {
        if ($period < 0) {
            throw new InvalidArgumentException('Cache period must be zero or positive');
        }
        self::initDefault();
        $storageKey = self::key($key);
        self::$instance->set($storageKey, self::encode($value), $period);
    }

    /** @return array{hit: bool, value: mixed} */
    public static function read(string $key): array
    {
        self::initDefault();
        $encoded = self::$instance->get(self::key($key));
        return $encoded === null
            ? ['hit' => false, 'value' => null]
            : ['hit' => true, 'value' => self::decode($encoded)];
    }

    public static function get(string $key, mixed $default = false): mixed
    {
        $result = self::read($key);
        return $result['hit'] ? $result['value'] : $default;
    }

    public static function has(string $key): bool
    {
        return self::read($key)['hit'];
    }

    public static function delete(string $key): void
    {
        self::initDefault();
        self::$instance->delete(self::key($key));
    }

    /** Computes on a miss; does not lock concurrent callers. */
    public static function remember(string $key, callable $callback, int $period = self::PERIOD_DEFAULT): mixed
    {
        if ($period < 0) {
            throw new InvalidArgumentException('Cache period must be zero or positive');
        }
        $result = self::read($key);
        if ($result['hit']) {
            return $result['value'];
        }
        $value = $callback();
        self::set($key, $value, $period);
        return $value;
    }

    public static function info(): string
    {
        return isset(self::$instance) ? self::$instance->info() : 'Null Cache';
    }

    /** Performs a health check, returning false on connection failure. */
    public static function status(): bool
    {
        return isset(self::$instance) && self::$instance->status();
    }

    private static function encode(mixed $value): string
    {
        self::validateValue($value, new \SplObjectStorage());
        return self::FORMAT . serialize($value);
    }

    private static function validateValue(mixed $value, \SplObjectStorage $seen, int $depth = 0): void
    {
        if (is_resource($value) || gettype($value) === 'resource (closed)' || $value instanceof \Closure) {
            throw new InvalidArgumentException('Resources and closures cannot be cached');
        }
        if (!is_array($value) && !is_object($value)) {
            return;
        }
        if (is_object($value)) {
            if ($seen->contains($value)) {
                return;
            }
            $seen->attach($value);
            $value = get_mangled_object_vars($value);
        }
        if ($depth >= 128) {
            throw new InvalidArgumentException('Cache value is too deeply nested or contains a recursive array');
        }
        foreach ($value as $item) {
            self::validateValue($item, $seen, $depth + 1);
        }
    }

    /** Read only from trusted cache servers: object restoration invokes PHP hooks. */
    private static function decode(string $encoded): mixed
    {
        if (!str_starts_with($encoded, self::FORMAT)) {
            throw new UnexpectedValueException('Unknown cache payload format');
        }
        set_error_handler(static function (int $severity, string $message): never {
            throw new UnexpectedValueException('Invalid serialized cache payload');
        });
        try {
            return unserialize(substr($encoded, strlen(self::FORMAT)), ['allowed_classes' => true]);
        } finally {
            restore_error_handler();
        }
    }
}
