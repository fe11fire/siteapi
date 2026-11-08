<?php
require dirname(__DIR__) . '/src/Root/Settings/Settings.php';
require dirname(__DIR__) . '/src/Root/Helpers/FileHelper.php';
require dirname(__DIR__) . '/src/Root/Helpers/StringHelper.php';
use SiteApi\Root\Settings\Settings;
$dir = sys_get_temp_dir() . '/siteapi-settings-' . bin2hex(random_bytes(6));
mkdir($dir);
$original = Settings::$directory;
Settings::$directory = $dir;
function check(bool $condition): void { if (!$condition) throw new RuntimeException('Settings check failed'); }
try {
    file_put_contents($dir . '/example.json', '{"name":"test","nested":{"key":"value"}}');
    file_put_contents($dir . '/notes.txt', 'not a settings file');
    Settings::init();
    check(Settings::get('example', 'name') === 'test');
    check(Settings::get('example', 'nested')->key === 'value');
    foreach (['{"secret":"PRIVATE" "broken":1}', '[]', 'null', '"text"', '42', ''] as $invalid) {
        file_put_contents($dir . '/example.json', $invalid);
        try {
            Settings::init();
            throw new LogicException('Invalid JSON accepted');
        } catch (RuntimeException $error) {
            check(str_contains($error->getMessage(), 'example.json'));
            check(!str_contains($error->getMessage(), 'PRIVATE'));
        }
    }
    file_put_contents($dir . '/example.json', '{}');
    Settings::init();
    check(Settings::get_Default('example', 'absent', 'fallback') === 'fallback');
    echo "Settings JSON validation checks passed\n";
} finally {
    unlink($dir . '/example.json');
    unlink($dir . '/notes.txt');
    rmdir($dir);
    Settings::$directory = $original;
}
