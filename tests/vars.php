<?php
require dirname(__DIR__) . '/src/Root/Http/Models/Vars.php';
use SiteApi\Root\Http\Models\Vars;
function check(bool $condition): void { if (!$condition) throw new RuntimeException('Vars check failed'); }
foreach (['userData', '_data', '$data', 'item42'] as $name) {
    ob_start();
    Vars::make_JS_var($name, ['text' => '</script><script>alert(1)</script>']);
    $html = ob_get_clean();
    check(str_starts_with($html, '<script>var ' . $name . ' = '));
    check(substr_count($html, '</script>') === 1);
}
foreach (['', 'user-name', '1name', 'window.data', 'var', 'class', 'await', 'null', 'x;alert(1)//', '</script>', "name\n"] as $name) {
    ob_start();
    try {
        Vars::make_JS_var($name, []);
        throw new LogicException('Invalid name accepted');
    } catch (InvalidArgumentException) {
        check(ob_get_contents() === '');
    } finally { ob_end_clean(); }
}
foreach (['make_JS_var', 'console_log'] as $method) {
    ob_start();
    try {
        if ($method === 'make_JS_var') Vars::make_JS_var('data', "\xFF");
        else Vars::console_log("\xFF");
        throw new LogicException('Invalid UTF-8 accepted');
    } catch (JsonException) {
        check(ob_get_contents() === '');
    } finally { ob_end_clean(); }
}
echo "JS identifiers, script escaping and JSON failure checks passed\n";
