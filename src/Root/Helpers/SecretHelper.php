<?php

namespace SiteApi\Root\Helpers;

use SiteApi\Root\Settings\Settings;

class SecretHelper
{
    private static array $secrets = [];

    public static function get(string $name): string
    {
        if (!isset($secrets[$name])) {
            self::$secrets[$name] = self::make($name);
        }

        return self::$secrets[$name];
    }


    private static function make(string $name): string
    {
        $code = $name;
        if (session_id()) {
            $code .= session_id();
        }
        $code .= Settings::get('app', 'id');
        return md5($code);
    }
}
