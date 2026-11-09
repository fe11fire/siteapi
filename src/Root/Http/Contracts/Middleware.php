<?php

namespace SiteApi\Root\Http\Contracts;

interface Middleware
{
    /**
     * Выполняет проверку или подготовку перед страницей маршрута.
     *
     * Возвращаемое значение Router не использует. Для завершения с JSON-ответом
     * используйте Response::error(...)->throwSend() внутри App.
     * @throws \Throwable Ошибка передаётся в App; ResponseReady обрабатывается как ответ.
     */
    public static function apply();
}
