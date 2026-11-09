<?php

namespace SiteApi\Root\Http;

use SiteApi\Root\Helpers\LogHelper;
use SiteApi\Root\Helpers\DatabaseErrorContext;
use SiteApi\Root\Settings\Settings;
use Throwable;

class ErrorHandler
{
    /**
     * Журналирует исключение и выводит нейтральный HTML/JSON-ответ 500.
     *
     * Учитывает env.folder при определении AJAX без требования инициализации Router.
     * При недоступной настройке префикса проверяет путь без него. Сбой логгера пишет в error_log.
     * Сам не очищает предыдущий вывод и не завершает выполнение: этим управляет App.
     * После отправки заголовков HTTP-статус и заголовки изменить невозможно.
     */
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
        $databaseError = DatabaseErrorContext::from($error);
        $context = [
            'error_id' => $errorId,
            'type' => $error::class,
            'message' => $databaseError !== null ? 'Database operation failed' : $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'path' => $path,
            'trace' => $trace,
        ];

        if ($databaseError !== null) $context['database'] = $databaseError;

        try {
            LogHelper::error('Unhandled request error', $context);
        } catch (Throwable $loggingError) {
            // Keep the original error available even when the configured logger fails.
            error_log('Unhandled request error: ' . json_encode($context, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));
            error_log('Error logger failed [' . $errorId . ']: ' . $loggingError::class);
        }

        // Router may not be initialized when configuration/bootstrap fails.
        try {
            $folder = Settings::getString('env', 'folder', '');
        } catch (Throwable) {
            $folder = '';
        }
        $isAjax = RequestPath::isAjax(is_string($path) ? $path : '', $folder);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: ' . ($isAjax ? 'application/json' : 'text/html') . '; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo $isAjax ? Templates::pageAjaxError($errorId) : Templates::pageError($errorId);
    }
}
