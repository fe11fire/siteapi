<?php
namespace SiteApi\Root\Providers\Cache;

use Memcached as Client;
use RuntimeException;
use SiteApi\Root\Settings\Settings;

class Memcached implements CacheContract
{
    public function __construct(private ?Client $client = null) {}

    private function connection(): Client
    {
        if ($this->client === null) {
            if (!class_exists(Client::class)) {
                throw new RuntimeException('PHP memcached extension is required');
            }
            $client = new Client();
            if (!$client->addServer(
                Settings::getString('memcached', 'host', 'memcached'),
                Settings::getInt('memcached', 'port', 11211)
            )) {
                throw new RuntimeException('Cannot configure Memcached server');
            }
            $this->client = $client;
        }
        return $this->client;
    }

    public function set(string $key, string $value, int $period): void
    {
        if ($period < 0) {
            throw new \InvalidArgumentException('Cache period must be zero or positive');
        }
        // Memcached interprets values above 30 days as Unix timestamps.
        $expiration = $period > 2592000 ? time() + $period : $period;
        $client = $this->connection();
        if (!$client->set($key, $value, $expiration)) {
            throw new RuntimeException('Memcached write failed: ' . $client->getResultCode());
        }
    }

    public function get(string $key): ?string
    {
        $client = $this->connection();
        $value = $client->get($key);
        $code = $client->getResultCode();
        if ($code === Client::RES_NOTFOUND) {
            return null;
        }
        if ($code !== Client::RES_SUCCESS) {
            throw new RuntimeException('Memcached read failed: ' . $code);
        }
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Expected an encoded cache string');
        }
        return $value;
    }

    public function delete(string $key): void
    {
        $client = $this->connection();
        if (!$client->delete($key) && $client->getResultCode() !== Client::RES_NOTFOUND) {
            throw new RuntimeException('Memcached delete failed: ' . $client->getResultCode());
        }
    }

    public function info(): string
    {
        return CacheEnum::MEMCACHED->value;
    }

    public function status(): bool
    {
        try {
            $versions = $this->connection()->getVersion();
            if (!is_array($versions) || $versions === []) {
                return false;
            }
            foreach ($versions as $version) {
                if (!is_string($version) || $version === '' || $version === '255.255.255') {
                    return false;
                }
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
