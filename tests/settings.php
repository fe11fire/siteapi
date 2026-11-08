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
    file_put_contents($dir . '/example.json', '{"enabled":false,"port":3306,"password":"00123","hosts":["example.com"],"nullable":null}');
    Settings::init();
    check(Settings::getBool('example', 'enabled') === false);
    check(Settings::getInt('example', 'port') === 3306);
    check(Settings::getString('example', 'password') === '00123');
    check(Settings::getArray('example', 'hosts') === ['example.com']);
    check(Settings::get_Default('example', 'nullable', 'fallback') === null);
    check(Settings::getBool('example', 'missing', default: false) === false);
    check(Settings::getInt('example', 'missing', default: 0) === 0);
    $originalEnv = getenv('EXAMPLE_TYPED_TEST');
    try {
        foreach ([['false', 'getBool', false], ['true', 'getBool', true], ['0', 'getBool', false],
            ['3306', 'getInt', 3306], ['00123', 'getString', '00123'], ['["a"]', 'getArray', ['a']]] as [$raw, $method, $expected]) {
            putenv('EXAMPLE_TYPED_TEST=' . $raw);
            check(Settings::$method('example', 'typed_test') === $expected);
        }
        foreach ([['nonsense', 'getBool'], ['', 'getBool'], ['3.5', 'getInt'],
            ['99999999999999999999999999', 'getInt'], ['{}', 'getArray'] ] as [$raw, $method]) {
            putenv('EXAMPLE_TYPED_TEST=' . $raw);
            try {
                Settings::$method('example', 'typed_test', default: 0);
                throw new LogicException('Invalid type accepted');
            } catch (UnexpectedValueException $error) {
                check(!str_contains($error->getMessage(), $raw) || $raw === '');
            }
        }
        putenv('EXAMPLE_TYPED_TEST');
        check(Settings::getString('example', 'typed_test', default: '') === '');
        try {
            Settings::getBool('example', 'nullable', default: false);
            throw new LogicException('NULL accepted');
        } catch (UnexpectedValueException) {}
        try {
            Settings::getString('example', 'port');
            throw new LogicException('Number accepted as string');
        } catch (UnexpectedValueException) {}
    } finally {
        putenv($originalEnv === false ? 'EXAMPLE_TYPED_TEST' : 'EXAMPLE_TYPED_TEST=' . $originalEnv);
    }
    echo "Settings JSON validation checks passed\n";
} finally {
    unlink($dir . '/example.json');
    unlink($dir . '/notes.txt');
    rmdir($dir);
    Settings::$directory = $original;
}
