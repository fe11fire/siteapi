<?php

namespace SiteApi\Root\Settings;

use SiteApi\Root\Settings\Settings;

class Mode
{
    const CONDITION_DEV = 'dev';
    const CONDITION_PROD = 'prod';
    const CONDITION_ANY = 'any';

    public static function printIf(
        string $text,
        string $mode = self::CONDITION_ANY
    ): string {
        if (
            ($mode != self::CONDITION_ANY) &&
            ($mode != Settings::get('env', 'mode'))
        ) {
            return '';
        }
        return $text;
    }
}
