<?php

namespace SiteApi\Root\Helpers;

use Carbon\Carbon;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use SiteApi\Root\Settings\Settings;

class LogHelper
{
    public static function log(
        string $message,
        array $params = [],
        $folder = 'common/',
        $debug = true,
        $status = Logger::INFO,
        $send = false,
        $tag = 'error'
    ) {
        $log = new Logger('name');
        $dir = Settings::get_Directory('logs') . '/' . Carbon::now()->format('Y') . '/' . $folder;
        if (!file_exists($dir) || !is_dir($dir)) {
            mkdir($dir, recursive: true);
        }

        $log->pushHandler(new StreamHandler($dir . Carbon::now()->format('m-d') . '.log', $status));

        if (count($params) == 0) {
            $params = ['Params' => 'empty'];
        } else {
            foreach ($params as $key => &$value) {
                if (is_object($value) || is_array($value)) {
                    $d = '';
                    foreach ($value as $k => $v) {
                        if (is_object($v) || is_array($v)) {
                            $d .= $k . ': ' . json_encode($v) . ', ';
                        } else {
                            $d .= $k . ': ' . $v . ', ';
                        }
                    }
                    $value = $d;
                } else {
                    if ($value != null) {
                        if (strlen($value) > 1000) {
                            $value = substr($value, 1, 1000) . '..';
                        }
                    }
                }
            }
        }

        if ($debug) {
            $bt = debug_backtrace();
            $Debug_str = '';
            foreach ($bt as $key) {
                $Debug_str .= $key['line'] . ' - ' . $key['file'] . PHP_EOL . '{' . json_encode($key['args']) . '}' . PHP_EOL;
            }
            $params = $params + ['debug' => $Debug_str];
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
