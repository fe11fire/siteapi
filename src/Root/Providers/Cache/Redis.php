<?php
namespace SiteApi\Root\Providers\Cache;

use Predis\Client;
use RuntimeException;
use SiteApi\Root\Settings\Settings;

class Redis implements CacheContract
{
    /**
     * Принимает готовый Predis-клиент либо откладывает создание соединения до операции.
     *
     * Стандартный клиент использует redis.host/port; пароль, TLS и database через эти настройки
     * не задаются. Для расширенной конфигурации передайте подготовленный клиент.
     */
    public function __construct(private ?Client $client = null) {}

    private function connection(): Client
    {
        return $this->client ??= new Client([
            'host' => Settings::getString('redis', 'host', 'redis'),
            'port' => Settings::getInt('redis', 'port', 6379),
        ]);
    }

    /** Keep operational failures consistent across cache adapters. */
    private function execute(callable $operation): mixed
    {
        try {
            return $operation($this->connection());
        } catch (\Throwable $error) {
            throw new RuntimeException('Redis operation failed', 0, $error);
        }
    }
    /**
     * Сохраняет строку в Redis с TTL в секундах; 0 без срока.
     *
     * Ключ передаётся напрямую без пространства имён CacheProvider.
     * @throws \InvalidArgumentException Отрицательный период.
     * @throws \RuntimeException Сбой сервера или неожиданный результат записи.
     */
    public function set(string $key, string $value, int $period): void
    {
        if ($period < 0) {
            throw new \InvalidArgumentException('Cache period must be zero or positive');
        }
        $result = $period === 0
            ? $this->execute(fn(Client $client) => $client->set($key, $value))
            : $this->execute(fn(Client $client) => $client->set($key, $value, 'EX', $period));
        if ((string) $result !== 'OK') {
            throw new RuntimeException('Redis write failed');
        }
    }

    /**
     * Возвращает строку Redis либо null при отсутствии ключа.
     * @throws \RuntimeException Ошибка соединения/операции.
     */
    public function get(string $key): ?string
    {
        return $this->execute(fn(Client $client) => $client->get($key));
    }

    /**
     * Удаляет ключ Redis; отсутствие не считается ошибкой.
     * @throws \RuntimeException Ошибка соединения/операции.
     */
    public function delete(string $key): void
    {
        $this->execute(fn(Client $client) => $client->del([$key]));
    }

    /**
     * Возвращает имя Redis без обращения к серверу.
     */
    public function info(): string
    {
        return CacheEnum::REDIS->value;
    }

    /**
     * Проверяет Redis командой PING; исключения преобразует в false.
     */
    public function status(): bool
    {
        try {
            return (string) $this->connection()->ping() === 'PONG';
        } catch (\Throwable) {
            return false;
        }
    }
}
