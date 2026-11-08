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

    private static function readDirectory(string $dir): array
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            throw new \RuntimeException('Cannot read directory: ' . $dir);
        }
        return $entries;
    }

    public static function list_Folders(string $dir, string $subdir = '', bool $subfolders = true): array
    {
        return self::collectFolders($dir, trim($subdir, '/'), $subfolders, []);
    }

    /** Track ancestors, so links may be traversed without following a cycle. */
    private static function collectFolders(string $dir, string $prefix, bool $recursive, array $ancestors): array
    {
        $entries = self::readDirectory($dir);
        $real = realpath($dir);
        if ($real === false) {
            throw new \RuntimeException('Cannot resolve directory: ' . $dir);
        }
        $ancestors[$real] = true;
        $folders = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            if (!is_dir($path)) continue;
            $relative = $prefix !== '' ? $prefix . '/' . $entry : $entry;
            $folders[] = $relative;
            if (!$recursive) continue;
            $target = realpath($path);
            if ($target === false) {
                throw new \RuntimeException('Cannot resolve directory: ' . $path);
            }
            if (!isset($ancestors[$target])) {
                $folders = array_merge($folders, self::collectFolders($path, $relative, true, $ancestors));
            }
        }
        return $folders;
    }

    public static function list_Files($dir): array
    {
        $ffs = self::readDirectory($dir);

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
