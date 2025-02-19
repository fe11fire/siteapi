<?php

namespace SiteApi\Root\Helpers;

class FileHelper
{

    public static function make_Folder($folder)
    {
        if (!file_exists($folder) || !is_dir($folder)) {
            mkdir($folder, recursive: true);
        }
    }

    public static function remove_Folders_without_files(string $dir, array $folders): array
    {
        for ($i = count($folders) + 1; $i >= 0; $i--) {
            if (!isset($folders[$i])) {
                continue;
            }
            $files = self::list_Files($dir . '/' . $folders[$i]);

            if (count($files) == 0) {
                unset($folders[$i]);
            }
        }
        return array_values($folders);
    }

    public static function remove_Folders_without_template(string $dir, array $folders, string $template): array
    {
        for ($i = count($folders) + 1; $i >= 0; $i--) {
            if (!isset($folders[$i])) {
                continue;
            }
            $files = self::list_Files($dir . '/' . $folders[$i]);

            $unset = true;
            foreach ($files as $filename) {
                if ($filename == $template) {
                    $unset = false;
                    break;
                }
            }

            if ($unset) {
                unset($folders[$i]);
            }
        }
        return array_values($folders);
    }

    public static function list_Folders(string $dir, string $subdir = '', bool $subfolders = true): array
    {
        $ffs = scandir($dir);

        $sub_ffs = [];
        unset($ffs[array_search('.', $ffs, true)]);
        unset($ffs[array_search('..', $ffs, true)]);

        if (count($ffs) < 1)
            return [];

        for ($i = count($ffs) + 1; $i >= 0; $i--) {
            if (!isset($ffs[$i])) {
                continue;
            }
            if (is_dir($dir . '/' . $ffs[$i])) {
                if ($subfolders) {
                    $sub_ffs = array_merge($sub_ffs, self::list_Folders($dir . '/' . $ffs[$i], $ffs[$i]));
                    if ($subdir != '') {
                        $ffs[$i] = $subdir . '/' . $ffs[$i];
                    }
                }
            } else {
                unset($ffs[$i]);
            }
        }
        return array_merge($ffs, $sub_ffs);
    }

    public static function list_Files($dir): array
    {
        if (!is_dir($dir)) {
            LogHelper::log('list_Files', ['error' => $dir . ' is not directory'], 'errors/');
            return [];
        }

        $ffs = scandir($dir);

        unset($ffs[array_search('.', $ffs, true)]);
        unset($ffs[array_search('..', $ffs, true)]);

        if (count($ffs) < 1)
            return [];

        for ($i = count($ffs) + 1; $i >= 0; $i--) {
            if (!isset($ffs[$i])) {
                continue;
            }
            if (is_dir($dir . '/' . $ffs[$i])) {
                unset($ffs[$i]);
            }
        }
        return $ffs;
    }
}
