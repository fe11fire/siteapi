<?php

namespace SiteApi\Root\Http;

/** Classifies a URL path without depending on initialized router state. */
final class RequestPath
{
    /**
     * Проверяет сегмент ajax в пути относительно папки приложения.
     *
     * Удаляет только полный префикс с границей /; похожие префиксы не совпадают.
     * @param string $path Только URL-путь без query string, не полный URI.
     * @param string $folder Префикс, например company/; крайние слеши несущественны.
     */
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
