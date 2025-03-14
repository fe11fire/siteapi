<?php

namespace SiteApi\Root\Providers\Logger;

use Monolog\Level;
use Monolog\Logger;

interface LoggerContract
{
    public static function get(string $folder, Level $status): Logger;
}
