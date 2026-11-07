<?php
/** Integration test: requires disposable Redis and Memcached servers. */
require dirname(__DIR__) . '/vendor/autoload.php';

use SiteApi\Root\Providers\Cache\{CacheEnum, CacheProvider};

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectException(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        check($error instanceof $class, 'Wrong exception: ' . $error::class);
        return;
    }
    throw new RuntimeException('Expected ' . $class);
}

final class CacheTestObject
{
    public function __construct(public string $name, private int $number) {}
}

$namespace = 'cache-test-' . bin2hex(random_bytes(8));
$reflection = new ReflectionProperty(CacheProvider::class, 'instance');
foreach (CacheEnum::cases() as $backend) {
    CacheProvider::init($backend, $namespace);
    check(CacheProvider::status(), $backend->value . ' is unavailable');
    $adapter = $reflection->getValue();
    $keys = [];
    $key = function (string $suffix) use (&$keys): string {
        $keys[] = $suffix;
        return $suffix;
    };
    try {
        $values = [false, null, true, 0, 42, -7, 1.25, '', 'Привет " & < >', "\0\xFF", [], ['a'=>false, 'b'=>null, 'nested'=>[1, 2]], new CacheTestObject('Иван', 42)];
        foreach ($values as $index => $value) {
            $name = $key('value-' . $index);
            CacheProvider::set($name, $value, 0);
            $result = CacheProvider::read($name);
            check(array_keys($result) === ['hit', 'value'] && $result['hit'] === true, 'Missing stored value');
            check(serialize($result['value']) === serialize($value), 'Type/value changed');
            check(CacheProvider::has($name), 'has() lost a stored value');
            check(serialize(CacheProvider::get($name, 'fallback')) === serialize($value), 'Default replaced a stored value');
        }
        $cycle = new stdClass();
        $cycle->self = $cycle;
        CacheProvider::set($key('object-cycle'), $cycle);
        $restored = CacheProvider::get('object-cycle');
        check($restored->self === $restored, 'Object identity not restored');

        check(!CacheProvider::read('missing')['hit'], 'Missing key reported as hit');
        check(CacheProvider::get('missing') === false, 'Legacy default changed');
        check(CacheProvider::get('missing', 'fallback') === 'fallback', 'Custom default lost');
        $calls = 0;
        $callback = function () use (&$calls) { $calls++; return null; };
        CacheProvider::remember($key('remember'), $callback);
        CacheProvider::remember('remember', $callback);
        check($calls === 1, 'remember() recomputed cached null');
        CacheProvider::delete('remember');
        CacheProvider::delete('remember');
        check(!CacheProvider::has('remember'), 'delete() failed');

        CacheProvider::set($key('long-ttl'), 'kept', 2592001);
        check(CacheProvider::get('long-ttl') === 'kept', 'TTL above 30 days expired immediately');
        CacheProvider::set($key('expiry'), 'short', 1);
        usleep(2100000);
        check(!CacheProvider::has('expiry'), 'TTL did not expire');
        CacheProvider::set($key('namespace'), 'private');
        CacheProvider::init($backend, $namespace . '-other');
        check(!CacheProvider::has('namespace'), 'Namespace isolation failed');
        CacheProvider::init($backend, $namespace);

        expectException(InvalidArgumentException::class, fn()=>CacheProvider::set('negative', 1, -1));
        expectException(InvalidArgumentException::class, fn()=>CacheProvider::get(''));
        expectException(InvalidArgumentException::class, fn()=>CacheProvider::init('invalid'));
        expectException(InvalidArgumentException::class, fn()=>CacheProvider::set('closure', static fn()=>1));
        $resource = fopen('php://memory', 'r+');
        try {
            expectException(InvalidArgumentException::class, fn()=>CacheProvider::set('resource', ['nested'=>$resource]));
        } finally {
            fclose($resource);
        }
        $payloadKey = 'siteapi:v1:' . hash('sha256', $namespace . "\0" . $key('corrupt'));
        $adapter->set($payloadKey, 'invalid', 60);
        expectException(UnexpectedValueException::class, fn()=>CacheProvider::read('corrupt'));
        $adapter->set($payloadKey, "SiteApi:PHP:1\0not serialized", 60);
        expectException(UnexpectedValueException::class, fn()=>CacheProvider::read('corrupt'));
        check(CacheProvider::get('value-0') === false, 'Error handler not restored after corruption');
        echo 'PASS: ', $backend->value, ' types, objects, misses, TTL, namespace, remember, validation and corrupt payloads', PHP_EOL;
    } finally {
        CacheProvider::init($backend, $namespace);
        foreach ($keys as $name) {
            CacheProvider::delete($name);
        }
    }
}

// A disconnected backend must throw on data operations, not masquerade as a miss.
$failedRedis = new \SiteApi\Root\Providers\Cache\Redis(new \Predis\Client([
    'host'=>'127.0.0.1', 'port'=>1, 'timeout'=>0.1, 'read_write_timeout'=>0.1,
]));
$failedMemcachedClient = new \Memcached();
$failedMemcachedClient->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 100);
$failedMemcachedClient->setOption(\Memcached::OPT_RETRY_TIMEOUT, 0);
$failedMemcachedClient->addServer('127.0.0.1', 1);
$failedMemcached = new \SiteApi\Root\Providers\Cache\Memcached($failedMemcachedClient);
foreach ([$failedRedis, $failedMemcached] as $adapter) {
    $reflection->setValue(null, $adapter);
    expectException(RuntimeException::class, fn()=>CacheProvider::read('unavailable'));
    expectException(RuntimeException::class, fn()=>CacheProvider::set('unavailable', 'value'));
    expectException(RuntimeException::class, fn()=>CacheProvider::delete('unavailable'));
    check(!CacheProvider::status(), 'Disconnected server reported healthy');
    echo 'PASS: ', $adapter->info(), ' connection failures throw; status returns false', PHP_EOL;
}
