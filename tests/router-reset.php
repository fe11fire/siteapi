<?php
require dirname(__DIR__) . '/src/Root/Http/Router.php';
use SiteApi\Root\Http\Router;
$arrays = ['routes', 'rules', 'middleware_files', 'middlewares', 'params'];
$strings = ['request', 'route', 'path', 'address'];
foreach ($arrays as $name) (new ReflectionProperty(Router::class, $name))->setValue(null, ['stale']);
foreach ($strings as $name) (new ReflectionProperty(Router::class, $name))->setValue(null, 'stale');
Router::reset();
foreach ($arrays as $name) {
    if ((new ReflectionProperty(Router::class, $name))->getValue() !== []) throw new RuntimeException($name);
}
foreach ($strings as $name) {
    if ((new ReflectionProperty(Router::class, $name))->getValue() !== '') throw new RuntimeException($name);
}
Router::reset();
echo "Router reset and repeated reset checks passed\n";
