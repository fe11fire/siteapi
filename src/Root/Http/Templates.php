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
        return self::page(
            'Попробуйте чуть позже',
            'Небольшая пауза',
            'Сейчас не удалось открыть страницу. Попробуйте вернуться через несколько минут.',
            '<div class="reference"><span>Код обращения</span><code>' . $id . '</code></div>',
            'error'
        );
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
        return self::page(
            'Страница не найдена',
            'Кажется, здесь пусто',
            'Возможно, страница переехала или в адрес закралась опечатка. Начнём с главной?',
            '',
            '404'
        );
    }

    private static function page(string $title, string $heading, string $description, string $extra, string $kind): string
    {
        $label = $kind === '404' ? '404 / СТРАНИЦА НЕ НАЙДЕНА' : 'ПОПРОБУЙТЕ ПОЗЖЕ';
        return <<<HTML
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 32px 20px;
    background: #090c09;
    color: #f0f5ee;
    font-family: system-ui, sans-serif;
}
main { width: 100%; max-width: 540px; }
.label { color: #b5ff29; font: 12px monospace; letter-spacing: .1em; }
h1 { margin: 24px 0 16px; font-size: clamp(30px, 6vw, 44px); line-height: 1.15; }
p { color: #9ba696; line-height: 1.7; }
a {
    display: inline-block;
    margin-top: 20px;
    padding: 8px 0;
    color: #b5ff29;
    text-decoration: none;
    border-bottom: 1px solid #526e28;
}
a:hover { border-color: #b5ff29; }
a:focus-visible { outline: 2px solid #b5ff29; outline-offset: 5px; }
.reference { margin-top: 32px; color: #9ba696; font-size: 12px; }
.reference code { display: block; margin-top: 8px; color: #b5ff29; overflow-wrap: anywhere; }
</style>
</head>
<body>
<main>
<div class="label">{$label}</div>
<h1>{$heading}</h1>
<p>{$description}</p>
<a href="/">На главную <span aria-hidden="true">↗</span></a>
{$extra}
</main>
</body>
</html>
HTML;
    }
}
