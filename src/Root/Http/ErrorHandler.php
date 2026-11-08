<?php

namespace SiteApi\Root\Http;

use SiteApi\Root\Helpers\LogHelper;
use Throwable;

class ErrorHandler
{
    public static function handle(Throwable $error): void
    {
        $errorId = uniqid('err-', true);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $trace = [];
        foreach ($error->getTrace() as $frame) {
            $trace[] = array_intersect_key($frame, array_flip([
                'file', 'line', 'class', 'type', 'function',
            ]));
        }
        $context = [
            'error_id' => $errorId,
            'type' => $error::class,
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'path' => $path,
            'trace' => $trace,
        ];

        try {
            LogHelper::error('Unhandled request error', $context);
        } catch (Throwable $loggingError) {
            // Keep the original error available even when the configured logger fails.
            error_log('Unhandled request error: ' . json_encode($context, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));
            error_log('Error logger failed [' . $errorId . ']: ' . $loggingError::class);
        }

        // Derive the response type independently of Router/Settings initialization.
        $isAjax = str_starts_with(trim(is_string($path) ? $path : '', '/'), 'ajax/')
            || trim(is_string($path) ? $path : '', '/') === 'ajax';

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: ' . ($isAjax ? 'application/json' : 'text/html') . '; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo $isAjax ? Templates::pageAjaxError($errorId) : Templates::pageError($errorId);
    }
}
