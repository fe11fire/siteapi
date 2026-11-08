<?php

namespace SiteApi;

use SiteApi\Root\Http\{Router, Templates, ErrorHandler};
use SiteApi\Root\Settings\Settings;

/** Front controller for ordinary PHP-FPM/Apache requests. */
final class App
{
    public static function run(string $root): void
    {
        $level = ob_get_level();
        $workingDirectory = getcwd();
        $settingsDirectory = Settings::$directory;
        ob_start();
        try {
            if (!is_dir($root) || !@chdir($root)) {
                throw new \RuntimeException('Cannot open application root');
            }
            Settings::init(allowMissing: true);
            Router::init($_SERVER['REQUEST_URI'] ?? '/', allowMissingMiddlewares: true);
            if (Router::route(Router::get_Route())) return;

            http_response_code(404);
            if (Router::is_Ajax()) {
                header('Content-Type: application/json; charset=utf-8');
                echo Templates::pageAjax();
                return;
            }
            if (!Router::route('404')) echo Templates::page404();
        } catch (\Throwable $error) {
            while (ob_get_level() > $level) {
                if (!@ob_end_clean()) {
                    error_log('Cannot discard application output buffer');
                    break;
                }
            }
            ob_start();
            ErrorHandler::handle($error);
        } finally {
            while (ob_get_level() > $level) {
                if (!@ob_end_flush()) {
                    error_log('Cannot flush application output buffer');
                    break;
                }
            }
            Settings::$directory = $settingsDirectory;
            if ($workingDirectory !== false) @chdir($workingDirectory);
        }
    }
}
