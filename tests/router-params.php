<?php
require dirname(__DIR__) . '/src/Root/Http/Router.php';
use SiteApi\Root\Http\Router;
Router::reset();
try {
    Router::apply_Param_by_Name('message', 'first');
    Router::apply_Param_by_Name('message', 'second');
    if (Router::get_Param_by_Name('message') !== 'second') throw new RuntimeException('Concatenation instead of replacement');
    foreach ([['status' => 'active'], 42, false, null, 'text'] as $value) {
        Router::apply_Param_by_Name('message', $value);
        if (Router::get_Params()['message'] !== $value) throw new RuntimeException('Value changed');
    }
    Router::apply_Param_by_Name('other', 'preserved');
    Router::apply_Param_by_Name('message', []);
    if (Router::get_Param_by_Name('other') !== 'preserved' || Router::get_Param_by_Name('message') !== []) throw new RuntimeException('Other parameters changed');
    echo "Router parameter creation, replacement and type checks passed\n";
} finally {
    Router::reset();
}
