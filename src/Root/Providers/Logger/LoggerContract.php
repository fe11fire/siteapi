<?php

namespace SiteApi\Root\Providers\Logger;

use Monolog\Level;
use Monolog\Logger;

interface LoggerContract
{
    /**
     * Создаёт логгер для папки и минимального уровня событий.
     *
     * Папка доверенная; файловая реализация использует её как часть пути.
     */
    public static function get(string $folder, Level $status): Logger;
    /**
     * Возвращает имя реализации логгера.
     */
    public static function info(): string;
}
