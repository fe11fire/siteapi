<?php

namespace SiteApi\Root\Providers\Logger;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class StdoutLogger implements LoggerContract
{
    /**
     * Создаёт логгер с обработчиком php://stdout и заданным минимальным уровнем.
     *
     * folder игнорируется. В веб-запросе stdout может попасть в тело HTTP-ответа;
     * используйте этот провайдер там, где вывод перехватывается инфраструктурой.
     */
    public static function get(string $folder, Level $status): Logger
    {
        $log = new Logger('name');
        $log->pushHandler(new StreamHandler("php://stdout", $status));
        return $log;
    }

    /**
     * Возвращает имя stdout-провайдера.
     */
    public static function info(): string
    {
        return LoggerEnum::STDOUT->value;
    }
}
