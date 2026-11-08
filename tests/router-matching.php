<?php
require dirname(__DIR__) . '/src/Root/Http/Router.php';
require dirname(__DIR__) . '/src/Root/Settings/Settings.php';
require dirname(__DIR__) . '/src/Root/Helpers/StringHelper.php';
use SiteApi\Root\Http\Router;
$rules = new ReflectionProperty(Router::class, 'rules');
$previous = getenv('ENV_FOLDER');
putenv('ENV_FOLDER=');
$file = tempnam(sys_get_temp_dir(), 'siteapi-route-');
file_put_contents($file, '<?php return;');
try {
    foreach ([['01', '1'], ['0e123', '0'], ['1', '01']] as [$configured, $request]) {
        Router::reset();
        $rules->setValue(null, [['name' => $configured, 'path' => '', 'url' => $file]]);
        if (Router::route($request) !== false) throw new RuntimeException('Different routes matched');
    }
    foreach (['01', '1', '0e123', 'about'] as $name) {
        Router::reset();
        $rules->setValue(null, [['name' => $name, 'path' => '', 'url' => $file]]);
        if (!Router::route($name)) throw new RuntimeException('Exact route did not match');
    }
    putenv('ENV_FOLDER=app/');
    Router::reset();
    $rules->setValue(null, [['name' => 'about', 'path' => '', 'url' => $file]]);
    if (!Router::route('app/about')) throw new RuntimeException('Prefixed route did not match');
    echo "Strict route matching checks passed\n";
} finally {
    Router::reset();
    putenv($previous === false ? 'ENV_FOLDER' : 'ENV_FOLDER=' . $previous);
    unlink($file);
}
