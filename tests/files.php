<?php
require dirname(__DIR__) . '/src/Root/Helpers/FileHelper.php';
use SiteApi\Root\Helpers\FileHelper;
function check(bool $condition): void { if (!$condition) throw new RuntimeException('Directory check failed'); }
$dir = sys_get_temp_dir() . '/siteapi-files-' . bin2hex(random_bytes(6));
mkdir($dir);
try {
    check(FileHelper::list_Files($dir) === []);
    check(FileHelper::list_Folders($dir) === []);
    file_put_contents($dir . '/example.txt', 'test');
    mkdir($dir . '/child');
    check(array_values(FileHelper::list_Files($dir)) === ['example.txt']);
    check(array_values(FileHelper::list_Folders($dir)) === ['child']);
    foreach (['list_Files', 'list_Folders'] as $method) {
        foreach ([$dir . '/missing', $dir . '/example.txt'] as $badPath) {
            try {
                FileHelper::$method($badPath);
                throw new LogicException('Expected directory read failure');
            } catch (RuntimeException $error) {
                check(str_contains($error->getMessage(), $badPath));
            }
        }
    }
} finally {
    unlink($dir . '/example.txt');
    rmdir($dir . '/child');
    rmdir($dir);
}
echo "Empty, populated, missing and non-directory path checks passed\n";
