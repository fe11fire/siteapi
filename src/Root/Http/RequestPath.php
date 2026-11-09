<?php

namespace SiteApi\Root\Http;

/** Classifies a URL path without depending on initialized router state. */
final class RequestPath
{
    public static function isAjax(string $path, string $folder = ''): bool
    {
        $path = trim($path, '/');
        $folder = trim($folder, '/');
        if ($folder !== '') {
            if (!str_starts_with($path, $folder . '/')) return false;
            $path = substr($path, strlen($folder) + 1);
        }
        return $path === 'ajax' || str_starts_with($path, 'ajax/');
    }
}
