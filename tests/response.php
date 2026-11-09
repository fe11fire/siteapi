<?php
/** Real HTTP checks, with a local server and no external services. */
require dirname(__DIR__) . '/vendor/autoload.php';
use SiteApi\Root\Http\Response;
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
check(json_decode(Response::ok(data: ['id' => 15])->body, true)['data']['id'] === 15, 'Success data');
check(json_decode(Response::ok()->body, true) === ['status'=>true,'code'=>0,'data'=>''], 'Default success format');
check(json_decode(Response::error('Invalid', data: ['field'=>'name'])->body, true) === ['status'=>false,'msg'=>'Invalid','data'=>['field'=>'name']], 'Public error data');
$response = Response::json(['ok'=>true]);
ob_start();
try {
    $response->throwSend();
    throw new LogicException('Response signal missing');
} catch (\SiteApi\Root\Http\ResponseReady $signal) {
    check($signal->response === $response, 'Signal preserves the response object');
}
check(ob_get_clean() === '', 'Creating and throwing a response does not output bytes');
foreach ([199, 204, 205, 304, 600] as $status) {
    try { Response::json([], $status); throw new LogicException('Invalid status accepted'); }
    catch (InvalidArgumentException) {}
}
foreach ([fn() => Response::json("\xff"), fn() => Response::json([], 204),
    fn() => Response::json([], headers: ['X-Test' => "bad\r\nInjected: true"]),
    fn() => Response::json([], headers: ['Content-Type' => 'text/html'])] as $invalid) {
    try { $invalid(); throw new LogicException('Invalid response accepted'); }
    catch (JsonException | InvalidArgumentException) {}
}
$root = sys_get_temp_dir() . '/siteapi-response-' . bin2hex(random_bytes(6));
mkdir($root); mkdir($root . '/routes'); mkdir($root . '/pages');
$process = null;
$oldFolder = getenv('ENV_FOLDER');
putenv('ENV_FOLDER=');
try {
    $autoload = var_export(dirname(__DIR__) . '/vendor/autoload.php', true);
    file_put_contents($root . '/routes/web.php', <<<'PHP'
<?php
class RejectResponse implements \SiteApi\Root\Http\Contracts\Middleware {
    public static function apply() { \SiteApi\Root\Http\Response::error('Нет доступа', 403)->throwSend(); }
}
return [
 ['name'=>'ok','path'=>'pages/','url'=>'ok.php'],
 ['name'=>'location','path'=>'pages/','url'=>'location.php'],
 ['name'=>'blocked','path'=>'pages/','url'=>'blocked.php','middlewares'=>[RejectResponse::class]],
 ['name'=>'ajax/invalid','path'=>'pages/','url'=>'invalid.php'],
];
PHP);
    // The entry point is responsible for not writing unrelated output after App returns.
    file_put_contents($root . '/index.php', '<?php require ' . $autoload . ';'
        . '$cwd=getcwd(); $level=ob_get_level(); $settings=\SiteApi\Root\Settings\Settings::$directory;'
        . '\SiteApi\App::run(__DIR__);'
        . 'if(getcwd()!==$cwd || ob_get_level()!==$level || \SiteApi\Root\Settings\Settings::$directory!==$settings) { echo "RESTORE_FAILED"; }');
    file_put_contents($root . '/pages/ok.php', <<<'PHP'
<?php
echo 'PARTIAL'; ob_start(); echo 'NESTED';
header('Location: /old'); header('Content-Length: 999');
header('Set-Cookie: response_test=kept; HttpOnly', false);
\SiteApi\Root\Settings\Settings::$directory = 'changed-by-handler';
try {
 \SiteApi\Root\Http\Response::ok(data: ['id'=>15], httpStatus:201, headers:['X-Test'=>'response'])->throwSend();
} finally { file_put_contents('finally.txt', 'done'); }
echo 'AFTER_SEND';
PHP);
    file_put_contents($root . '/pages/location.php', '<?php \SiteApi\Root\Http\Response::json([], headers: ["Location" => "/next"])->throwSend();');
    file_put_contents($root . '/pages/blocked.php', '<?php echo "PAGE_MUST_NOT_RUN";');
    file_put_contents($root . '/pages/invalid.php', '<?php echo "PARTIAL_SECRET"; \SiteApi\Root\Http\Response::json("\xff")->throwSend();');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException($error);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $process = proc_open([PHP_BINARY, '-S', $address, $root . '/index.php'],
        [0=>['pipe','r'], 1=>['file',$root.'/server.log','a'], 2=>['file',$root.'/server.log','a']], $pipes, $root);
    if (!is_resource($process)) throw new RuntimeException('Cannot start test server');
    fclose($pipes[0]);
    $ready = false;
    for ($i=0; $i<100; $i++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, .05);
        if ($connection) { fclose($connection); $ready=true; break; }
        usleep(20000);
    }
    check($ready, 'Test server ready');
    $request = function (string $path, string $method='GET') use ($address): array {
        $curl = curl_init('http://' . $address . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HEADER=>true, CURLOPT_TIMEOUT=>5]);
        if ($method==='HEAD') curl_setopt($curl, CURLOPT_NOBODY, true);
        $raw = curl_exec($curl);
        if ($raw===false) throw new RuntimeException(curl_error($curl));
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);
        return [$status, strtolower(substr($raw,0,$size)), substr($raw,$size)];
    };
    [$status,$headers,$body] = $request('/ok');
    check($status===201, 'HTTP status');
    check(str_contains($headers,'content-type: application/json; charset=utf-8'), 'JSON content type');
    check(str_contains($headers,'x-test: response'), 'Custom header');
    check(str_contains($headers,'set-cookie: response_test=kept; httponly'), 'Existing cookie retained');
    check(!str_contains($headers,'location:') && !str_contains($headers,'content-length: 999'), 'Stale headers removed');
    check(json_decode($body,true,512,JSON_THROW_ON_ERROR)===['status'=>true,'code'=>0,'data'=>['id'=>15]], 'Only JSON, no partial output');
    check(file_get_contents($root.'/finally.txt')==='done', 'Handler finally executed');
    check(!is_dir($root.'/siteapilogs'), 'Response control flow is not logged as an error');
    [$status,$headers,$body] = $request('/location');
    check($status===200 && str_contains($headers, 'location: /next'), 'Explicit status survives Location header');
    [$status,$headers,$body] = $request('/blocked');
    check($status===403 && json_decode($body,true)===['status'=>false,'msg'=>'Нет доступа'], 'Middleware stops the page');
    [$status,$headers,$body] = $request('/ok','HEAD');
    check($status===201 && $body==='', 'HEAD has no body');
    [$status,$headers,$body] = $request('/ajax/invalid');
    check($status===500 && !str_contains($body,'PARTIAL_SECRET') && is_array(json_decode($body,true)), 'Encoding failure uses ErrorHandler');
    echo "Response JSON, HTTP headers/status, middleware, buffers, finally, HEAD and encoding checks passed\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    putenv($oldFolder===false ? 'ENV_FOLDER' : 'ENV_FOLDER='.$oldFolder);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}
