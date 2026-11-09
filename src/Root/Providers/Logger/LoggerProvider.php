<?php

namespace SiteApi\Root\Providers\Logger;

use Monolog\Level;
use Monolog\Logger;

class LoggerProvider implements LoggerContract
{
    /**
     * Возвращает логгер выбранного провайдера; по умолчанию используется FileLogger.
     *
     * Логгер создаётся при каждом вызове, экземпляры не кэшируются.
     * @param string $folder Доверенный подкаталог журнала.
     * @param Level $status Минимальный уровень обработчика.
     * @throws \Throwable Ошибка создания каталога или обработчика.
     */
    public static function get(string $folder, Level $status): Logger
    {
        self::initDefault();
        return self::$instance::get($folder, $status);
    }

    private static LoggerContract $instance;

    /**
     * Выбирает глобальный провайдер логирования по enum или строковому имени.
     *
     * Повторный вызов заменяет выбранный провайдер.
     * @throws \InvalidArgumentException Неизвестное имя провайдера.
     */
    public static function init(LoggerEnum|string $logger): void
    {
        $provider = $logger instanceof LoggerEnum ? $logger : LoggerEnum::tryFrom($logger);
        if ($provider === null) {
            $allowed = implode(', ', array_map(fn(LoggerEnum $case) => $case->value, LoggerEnum::cases()));
            throw new \InvalidArgumentException('Unknown logger "' . $logger . '". Allowed values: ' . $allowed);
        }
        self::$instance = $provider->getProvider();
    }

    private static function initDefault()
    {
        if (!isset(self::$instance)) {
            self::$instance = LoggerEnum::FILE->getProvider();
        }
    }

    /**
     * Возвращает имя провайдера; при отсутствии выбора инициализирует файловый.
     */
    public static function info(): string
    {
        self::initDefault();
        return self::$instance::info();
    }
}
