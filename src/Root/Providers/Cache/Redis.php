<?php
namespace SiteApi\Root\Providers\Cache;

use Predis\Client;
use RuntimeException;
use SiteApi\Root\Settings\Settings;

class Redis implements CacheContract
{
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

    public function get(string $key): ?string
    {
        return $this->execute(fn(Client $client) => $client->get($key));
    }

    public function delete(string $key): void
    {
        $this->execute(fn(Client $client) => $client->del([$key]));
    }

    public function info(): string
    {
        return CacheEnum::REDIS->value;
    }

    public function status(): bool
    {
        try {
            return (string) $this->connection()->ping() === 'PONG';
        } catch (\Throwable) {
            return false;
        }
    }
}
