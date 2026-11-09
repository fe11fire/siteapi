<?php

namespace SiteApi\Root\Settings;

use SiteApi\Root\Settings\Settings;

class Mode
{
    const CONDITION_DEV = 'dev';
    const CONDITION_PROD = 'prod';
    const CONDITION_ANY = 'any';

    /**
     * Возвращает text для режима any или совпадающего env.mode; иначе пустую строку.
     *
     * Несмотря на имя, ничего не выводит. Текст не экранируется.
     * @throws \RuntimeException Для режима кроме any отсутствует env.mode.
     * @throws \UnexpectedValueException env.mode не строка.
     */
    public static function printIf(
        string $text,
        string $mode = self::CONDITION_ANY
    ): string {
        if (
            ($mode != self::CONDITION_ANY) &&
            ($mode != Settings::getString('env', 'mode'))
        ) {
            return '';
        }
        return $text;
    }
}
