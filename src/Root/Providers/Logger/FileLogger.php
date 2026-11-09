<?php

namespace SiteApi\Root\Providers\Logger;

use Carbon\Carbon;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use SiteApi\Root\Settings\Settings;
use SiteApi\Root\Helpers\FileHelper;

class FileLogger implements LoggerContract
{
    /**
     * Создаёт новый Monolog с файлом logs/{год}/{folder}/{месяц-день}.log.
     *
     * Путь logs берётся из настроек. folder должен быть доверенным: .. не блокируется.
     * Каталоги создаются автоматически; ротация/удаление старых файлов не выполняются.
     * @param Level $status Минимальный уровень обработчика.
     * @throws \Throwable Ошибка настроек, каталога или файлового обработчика.
     */
    public static function get(string $folder, Level $status): Logger
    {
        $now = Carbon::now();
        $base = rtrim(Settings::get_Directory_Default('logs', 'siteapilogs'), '/');
        $folder = trim($folder, '/');
        $dir = $base . '/' . $now->format('Y') . ($folder !== '' ? '/' . $folder : '');
        FileHelper::make_Folder($dir);

        $log = new Logger('name');
        $log->pushHandler(new StreamHandler($dir . '/' . $now->format('m-d') . '.log', $status));
        return $log;
    }

    /**
     * Возвращает имя файлового провайдера без открытия файла.
     */
    public static function info(): string
    {
        return LoggerEnum::FILE->value;
    }
}
