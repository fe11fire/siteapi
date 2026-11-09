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

    /**
     * Выбирает провайдер и пространство ключей, не подключаясь к серверу сразу.
     *
     * Повторный вызов заменяет провайдер и prefix; prefix должен различаться у приложений.
     * @throws \InvalidArgumentException Неизвестное имя провайдера (регистр значим).
     */
    public static function init(CacheEnum|string $name, string $prefix = 'siteapi'): void
    {
        $type = $name instanceof CacheEnum ? $name : CacheEnum::tryFrom($name);
        if ($type === null) {
            throw new InvalidArgumentException('Unknown cache provider: ' . $name);
        }
        self::$instance = $type->getProvider();
        self::$prefix = $prefix;
    }

    /**
     * Выбирает Memcached, если провайдер ещё не задан; соединение остаётся ленивым.
     */
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

    /**
     * Сериализует PHP-значение и записывает его в пространство ключей провайдера.
     *
     * Ключ хешируется; ресурсы, замыкания и чрезмерная вложенность не поддерживаются.
     * Объекты могут вызывать свои методы сериализации. Используйте только доверенный кэш.
     * @param int $period TTL в секундах; 0 без срока, отрицательные значения запрещены.
     * @throws \InvalidArgumentException Пустой ключ, неверный TTL или неподдерживаемое значение.
     * @throws \Throwable Ошибка сериализации или провайдера.
     */
    public static function set(string $key, mixed $value, int $period = self::PERIOD_DEFAULT): void
    {
        if ($period < 0) {
            throw new InvalidArgumentException('Cache period must be zero or positive');
        }
        self::initDefault();
        $storageKey = self::key($key);
        self::$instance->set($storageKey, self::encode($value), $period);
    }

    /**
     * Читает кэш, различая промах и сохранённые null/false.
     *
     * Восстанавливает PHP-объекты с allowed_classes=true и возможным вызовом PHP-хуков.
     * Сервер кэша и записываемые значения должны быть доверенными; ошибки не скрываются.
     * @return array{hit: bool, value: mixed}
     * @throws \UnexpectedValueException Неизвестный или повреждённый формат записи.
     * @throws \Throwable Ошибка провайдера или восстановления объекта.
     */
    public static function read(string $key): array
    {
        self::initDefault();
        $encoded = self::$instance->get(self::key($key));
        return $encoded === null
            ? ['hit' => false, 'value' => null]
            : ['hit' => true, 'value' => self::decode($encoded)];
    }

    /**
     * Возвращает сохранённое значение либо default при промахе.
     *
     * Сохранённые false/null не являются промахом; для явного hit используйте read().
     * Ошибки сервера и декодирования не подменяются default.
     * @see self::read()
     */
    public static function get(string $key, mixed $default = false): mixed
    {
        $result = self::read($key);
        return $result['hit'] ? $result['value'] : $default;
    }

    /**
     * Проверяет наличие записи через read(), включая декодирование значения.
     *
     * Может восстановить объект и вызвать его PHP-хуки; ошибки кэша не скрываются.
     * @see self::read()
     */
    public static function has(string $key): bool
    {
        return self::read($key)['hit'];
    }

    /**
     * Удаляет запись в текущем пространстве ключей; отсутствие записи допустимо.
     *
     * Ошибки провайдера не скрываются. Пустой ключ запрещён.
     */
    public static function delete(string $key): void
    {
        self::initDefault();
        self::$instance->delete(self::key($key));
    }

    /**
     * Возвращает кэш либо вычисляет callback, сохраняет и возвращает результат.
     *
     * Блокировки нет: параллельные запросы могут выполнить callback одновременно.
     * Ошибки callback и кэша распространяются; при ошибке записи результат не возвращается.
     * @param int $period TTL в секундах, 0 без срока; отрицательный запрещён.
     */
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

    /**
     * Возвращает имя выбранного провайдера или Null Cache без автоматической инициализации.
     */
    public static function info(): string
    {
        return isset(self::$instance) ? self::$instance->info() : 'Null Cache';
    }

    /**
     * Проверяет доступность уже выбранного провайдера.
     *
     * До init()/первой операции возвращает false; ошибки соединения преобразуются в false.
     */
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
