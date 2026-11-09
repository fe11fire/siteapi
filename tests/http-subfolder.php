<?php
require dirname(__DIR__) . '/vendor/autoload.php';
use SiteApi\App;
use SiteApi\Root\Http\{Router, RequestPath};
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
foreach ([
    ['ajax', '', true], ['ajax/save', '', true], ['ajax-other', '', false],
    ['/company/ajax/', 'company/', true], ['/company/ajax/save', 'company/', true],
    ['/company/ajax-other', 'company/', false], ['/company-other/ajax', 'company/', false],
    ['/ajax/save', 'company/', false], ['/company', 'company/', false],
    ['/sites/company/ajax/save', 'sites/company/', true],
] as [$path, $folder, $expected]) {
    check(RequestPath::isAjax($path, $folder) === $expected, 'Path classification: ' . $path);
}
$root = sys_get_temp_dir() . '/siteapi-subfolder-' . bin2hex(random_bytes(6));
mkdir($root);
$oldFolder = getenv('ENV_FOLDER');
$cwd = getcwd();
try {
    $cases = [
        ['', '/missing', 404, false, false],
        ['company/', '/company/missing', 404, false, false],
        ['company/', '/company/ajax/missing?x=1', 404, true, false],
        ['company/', '/company/ajax', 404, true, false],
        ['company/', '/company/ajax/broken', 500, true, false],
        ['company/', '/company/broken', 500, false, false],
        ['company/', '/company-other/ajax/missing', 404, false, false],
        ['sites/company/', '/sites/company/ajax/broken', 500, true, false],
        ['', '/ajax/broken', 500, true, false],
        ['', '/ajax/missing', 404, true, false],
        // Failure before Router::init: classification still uses ENV_FOLDER.
        ['company/', '/company/ajax/broken', 500, true, true],
    ];
    foreach ($cases as $index => [$folder, $uri, $expectedStatus, $json, $badSettings]) {
        $app = $root . '/case-' . $index;
        mkdir($app); mkdir($app . '/routes'); mkdir($app . '/pages');
        file_put_contents($app . '/routes/web.php', '<?php return '
            . var_export([
                ['name'=>'404','path'=>'pages/','url'=>'not-found.php'],
                ['name'=>'broken','path'=>'pages/','url'=>'broken.php'],
                ['name'=>'ajax/broken','path'=>'pages/','url'=>'broken.php'],
            ], true) . ';');
        file_put_contents($app . '/pages/not-found.php', '<?php echo "CUSTOM_NOT_FOUND";');
        file_put_contents($app . '/pages/broken.php', '<?php echo "PARTIAL_SECRET"; throw new RuntimeException("test failure");');
        if ($badSettings) {
            mkdir($app . '/settings'); mkdir($app . '/settings/main');
            file_put_contents($app . '/settings/main/env.json', '{invalid');
        }
        putenv('ENV_FOLDER=' . $folder);
        $_SERVER['REQUEST_URI'] = $uri;
        Router::reset();
        http_response_code(200);
        ob_start();
        $level = ob_get_level();
        App::run($app);
        check(ob_get_level() === $level, 'Preserve outer buffer');
        $body = ob_get_clean();
        check(http_response_code() === $expectedStatus, 'Status: ' . $uri);
        check(getcwd() === $cwd, 'Restore working directory');
        check(!str_contains($body, 'PARTIAL_SECRET'), 'Discard partial response');
        if ($json) {
            check(is_array(json_decode($body, true)), 'JSON: ' . $uri);
        } elseif ($expectedStatus === 404) {
            check($body === 'CUSTOM_NOT_FOUND', 'Custom 404: ' . $uri);
        } else {
            check(str_contains(strtolower($body), '<!doctype html>'), 'HTML 500: ' . $uri);
        }
    }
    echo "Root/subfolder custom 404, AJAX 404/500, prefix boundaries and bootstrap failure checks passed\n";
} finally {
    Router::reset();
    putenv($oldFolder === false ? 'ENV_FOLDER' : 'ENV_FOLDER=' . $oldFolder);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}
