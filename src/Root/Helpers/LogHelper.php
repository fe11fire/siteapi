<?php

namespace SiteApi\Root\Helpers;

use Monolog\Level;
use SiteApi\Root\Providers\Logger\LoggerProvider;

class LogHelper
{
    /** Mask known secret fields before the context reaches a logger. */
    private static function redactContext(mixed $value, int $depth = 0): mixed
    {
        if ($depth >= 20) {
            return '[MAX_DEPTH]';
        }
        if (is_object($value)) {
            // Inspect public properties only; do not invoke custom serializers.
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $normalized = strtolower(str_replace(['-', '_', ' '], '', (string) $key));
            if (in_array($normalized, [
                'password', 'passwd', 'pwd', 'secret', 'clientsecret',
                'token', 'accesstoken', 'refreshtoken', 'idtoken', 'bottoken',
                'authorization', 'proxyauthorization', 'cookie', 'setcookie',
                'apikey', 'xapikey', 'privatekey',
            ], true)) {
                $result[$key] = '[REDACTED]';
            } else {
                $result[$key] = self::redactContext($item, $depth + 1);
            }
        }
        return $result;
    }

    public static function log(
        string $message,
        array $params = [],
        $folder = 'common/',
        $debug = false,
        $status = Level::Info,
        $send = false,
        $tag = 'error'
    ) {
        $log = LoggerProvider::get($folder, $status);
        $params = self::redactContext($params);

        if (count($params) == 0) {
            $params = ['Params' => 'empty'];
        }

        if ($debug) {
            $trace = [];
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20) as $frame) {
                $trace[] = array_intersect_key($frame, array_flip([
                    'file', 'line', 'class', 'type', 'function',
                ]));
            }
            $params = $params + ['debug' => $trace];
        }
        $log->log($status, $message, $params);
    }

    public static function dnd($val, string $message = 'Dump Not Die')
    {
        self::log($message, [$val], 'dump/');
    }

    public static function error(
        string $message,
        array $params = []
    ) {
        self::log($message, $params, 'errors/');
    }
}
