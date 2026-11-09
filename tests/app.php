<?php
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'SiteApi\\')) {
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
        if (is_file($file)) require $file;
    }
});
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$root = sys_get_temp_dir() . '/siteapi-app-' . bin2hex(random_bytes(6));
$workingDirectory = getcwd();
$oldEnv = getenv('ENV_FOLDER');
putenv('ENV_FOLDER=');
function scaffold(string $root): int {
    $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/bin/siteapi', 'init', $root],
        [0 => ['pipe','r'], 1 => ['file',$nullDevice,'a'], 2 => ['file',$nullDevice,'a']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start scaffold process');
    fclose($pipes[0]);
    return proc_close($process);
}
try {
    check(scaffold($root) === 0, 'Generate application');
    $original = file_get_contents($root . '/pages/home.php');
    check(scaffold($root) !== 0, 'Refuse overwrite');
    check(file_get_contents($root . '/pages/home.php') === $original, 'Preserve existing content');
    file_put_contents($root . '/pages/broken.php', '<?php echo "PARTIAL_SECRET"; throw new RuntimeException("test failure");');
    file_put_contents($root . '/routes/web.php', '<?php return [["name"=>"","path"=>"pages/","url"=>"home.php"],["name"=>"broken","path"=>"pages/","url"=>"broken.php"],["name"=>"ajax/broken","path"=>"pages/","url"=>"ajax-broken.php"]];');
    file_put_contents($root . '/pages/ajax-broken.php', '<?php echo "PARTIAL_SECRET"; throw new RuntimeException("test failure");');
    foreach ([['/', 200], ['/missing', 404], ['/ajax/missing', 404], ['/broken', 500], ['/ajax/broken', 500]] as [$uri, $status]) {
        \SiteApi\Root\Http\Router::reset();
        $_SERVER['REQUEST_URI'] = $uri;
        http_response_code(200);
        ob_start();
        $level = ob_get_level();
        \SiteApi\App::run($root);
        check(ob_get_level() === $level, 'Preserve outer buffer');
        $output = ob_get_clean();
        check(http_response_code() === $status, 'Correct HTTP status for ' . $uri);
        check(!str_contains($output, 'PARTIAL_SECRET'), 'Discard partial output');
        if (str_starts_with($uri, '/ajax/')) check(is_array(json_decode($output, true)), 'JSON response');
        if ($uri === '/') check(str_contains($output, 'Приложение работает'), 'Generated home page');
        check(getcwd() === $workingDirectory, 'Restore working directory');
    }
    check(!file_exists($root . '/settings') && !file_exists($root . '/scripts'), 'No empty configuration directories required');
    echo "Scaffold, overwrite protection, App startup, 404/500 and buffer checks passed\n";
} finally {
    putenv($oldEnv === false ? 'ENV_FOLDER' : 'ENV_FOLDER=' . $oldEnv);
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
        rmdir($root);
    }
}
