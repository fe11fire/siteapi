<?php
namespace SiteApi\Root\Http;

class Templates
{
    public static function pageAjax(): string
    {
        return json_encode([0, 1, 'Ошибка при отправке данных']);
    }

    public static function pageError(string $errorId): string
    {
        $id = htmlspecialchars($errorId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Попробуйте позже</title>'
            . '<body><main><h1>Сейчас не удалось открыть страницу</h1>'
            . '<p>Попробуйте ещё раз позже.</p><p>Код обращения: ' . $id . '</p></main></body></html>';
    }

    public static function pageAjaxError(string $errorId): string
    {
        return json_encode([
            'success' => false,
            'message' => 'Сейчас не удалось выполнить запрос. Попробуйте ещё раз позже.',
            'error_id' => $errorId,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function page404(): string
    {
        return '<div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;"><div><b>Page 404 not found!</b></div></div>';
    }
}
