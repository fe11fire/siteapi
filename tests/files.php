<?php
require dirname(__DIR__) . '/src/Root/Helpers/FileHelper.php';
use SiteApi\Root\Helpers\FileHelper;
function check(bool $condition): void { if (!$condition) throw new RuntimeException('Directory check failed'); }
$dir = sys_get_temp_dir() . '/siteapi-files-' . bin2hex(random_bytes(6));
mkdir($dir);
try {
    check(FileHelper::list_Files($dir) === []);
    check(FileHelper::list_Folders($dir) === []);
    FileHelper::make_Folder($dir); // Already exists.
    FileHelper::make_Folder($dir . '/new/nested');
    check(is_dir($dir . '/new/nested'));
    FileHelper::make_Folder($dir . '/new/nested');
    rmdir($dir . '/new/nested');
    rmdir($dir . '/new');
    file_put_contents($dir . '/example.txt', 'test');
    foreach ([$dir . '/example.txt', $dir . '/example.txt/child'] as $blocked) {
        try {
            FileHelper::make_Folder($blocked);
            throw new LogicException('Expected creation failure');
        } catch (RuntimeException $error) {
            check(str_contains($error->getMessage(), $blocked));
        }
    }
    mkdir($dir . '/child');
    check(array_values(FileHelper::list_Files($dir)) === ['example.txt']);
    check(array_values(FileHelper::list_Folders($dir)) === ['child']);
    mkdir($dir . '/child/deep/leaf', 0777, true);
    check(FileHelper::list_Folders($dir) === ['child', 'child/deep', 'child/deep/leaf']);
    check(FileHelper::list_Folders($dir, '', false) === ['child']);
    check(FileHelper::list_Folders($dir, 'prefix', true) === ['prefix/child', 'prefix/child/deep', 'prefix/child/deep/leaf']);
    check(FileHelper::list_Folders($dir, 'prefix', false) === ['prefix/child']);
    symlink($dir, $dir . '/child/deep/leaf/back');
    check(FileHelper::list_Folders($dir) === ['child', 'child/deep', 'child/deep/leaf', 'child/deep/leaf/back']);
    unlink($dir . '/child/deep/leaf/back');
    rmdir($dir . '/child/deep/leaf');
    rmdir($dir . '/child/deep');
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
    if (is_link($dir . '/child/deep/leaf/back')) unlink($dir . '/child/deep/leaf/back');
    if (is_dir($dir . '/child/deep/leaf')) rmdir($dir . '/child/deep/leaf');
    if (is_dir($dir . '/child/deep')) rmdir($dir . '/child/deep');
    rmdir($dir . '/child');
    rmdir($dir);
}
echo "Empty, populated, missing and non-directory path checks passed\n";
