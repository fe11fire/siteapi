<?php
require dirname(__DIR__) . '/src/Root/Http/Router.php';
$build = new ReflectionMethod(\SiteApi\Root\Http\Router::class, 'buildRedirectUrl');
foreach ([
    ['search', ['q' => 'чай & кофе#'], 'search?q=' . rawurlencode('чай & кофе#')],
    ['search?sort=date#results', ['q' => 'a&b'], 'search?sort=date&q=a%26b#results'],
    ['search?', ['page' => 2], 'search?page=2'],
    ['search?x=1&', ['page' => 2], 'search?x=1&page=2'],
    ['search#results', [], 'search#results'],
    ['search', ['filter' => ['status' => 'new']], 'search?filter%5Bstatus%5D=new'],
    ['search', null, 'search'],
] as [$route, $params, $expected]) {
    if ($build->invoke(null, $route, $params) !== $expected) throw new RuntimeException('Redirect URL mismatch');
}
try {
    $build->invoke(null, "search\r\nX-Test: value", []);
    throw new LogicException('Line break accepted');
} catch (InvalidArgumentException) {}
echo "Safe redirect URL checks passed\n";
