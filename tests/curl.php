<?php
/** Local HTTP integration checks; logging is replaced to inspect disclosure. */
namespace SiteApi\Root\Helpers {
    class LogHelper {
        public static array $entries = [];
        public static function error($message, $context): void { self::$entries[] = [$message, $context]; }
        public static function log($message, $context, $folder): void { self::$entries[] = [$message, $context]; }
    }
}
namespace {
    require dirname(__DIR__) . '/src/Root/Clients/CURL/Curl.php';
    use SiteApi\Root\Clients\CURL\Curl;
    function check(bool $value): void { if (!$value) throw new RuntimeException('CURL check failed'); }
    class TestCurl extends Curl {
        public function point(string $url): void { curl_setopt($this->curl, CURLOPT_URL, $url); }
        public function timeout(int $ms): void { curl_setopt($this->curl, CURLOPT_TIMEOUT_MS, $ms); }
    }
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$server) throw new RuntimeException($error);
    $address = stream_socket_get_name($server, false);
    fclose($server);
    $router = tempnam(sys_get_temp_dir(), 'siteapi-http-');
    file_put_contents($router, '<?php $path=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH); if($path==="/slow") usleep(300000); if($path==="/error") {http_response_code(500);echo "{\\"detail\\":\\"PRIVATE\\"}";} elseif($path==="/empty") http_response_code(204); elseif($path==="/invalid") echo "not json PRIVATE"; else echo "{\\"ok\\":true}";');
    $process = proc_open([PHP_BINARY, '-S', $address, $router], [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes);
    try {
        $ready = false;
        for ($i = 0; $i < 100; $i++) {
            $socket = @stream_socket_client('tcp://' . $address, $errno, $error, .05);
            if ($socket) { fclose($socket); $ready = true; break; }
            usleep(10000);
        }
        check($ready);
        $client = new TestCurl('http://' . $address . '/ok?token=PRIVATE', [], 'POST', ['secret' => 'PRIVATE']);
        check($client->exec() && $client->get_result()->ok && $client->get_error() === null);
        $client->point('http://' . $address . '/error');
        check(!$client->exec() && $client->get_error() === 'http_error' && $client->get_http_code() === 500);
        check($client->get_result()->detail === 'PRIVATE');
        $client->point('http://' . $address . '/invalid');
        check(!$client->exec() && $client->get_error() === 'invalid_json' && $client->get_result() === null);
        $client->point('http://' . $address . '/empty');
        check($client->exec() && $client->get_http_code() === 204 && $client->get_result() === null);
        $client->point('http://' . $address . '/slow'); $client->timeout(30);
        check(!$client->exec() && $client->get_error() === 'timeout');
        $client->point('http://127.0.0.1:1'); $client->timeout(1000);
        check(!$client->exec() && $client->get_error() === 'connection_failed' && $client->get_http_code() === 0);
        check(!str_contains(json_encode(\SiteApi\Root\Helpers\LogHelper::$entries), 'PRIVATE'));
        echo "CURL HTTP, JSON, timeout, connection failure and safe logging checks passed\n";
    } finally {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        unlink($router);
    }
}
