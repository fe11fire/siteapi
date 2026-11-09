<?php

namespace SiteApi\Root\Http;

/** @internal Control flow handled by App; must not be treated as an application error. */
final class ResponseReady extends \RuntimeException
{
    /**
     * Создаёт внутренний сигнал с готовым ответом для перехвата в App.
     *
     * Не журналируется как ошибка; прикладной код должен пропускать его в App.
     * @internal Используйте Response::throwSend() вместо ручного создания сигнала.
     */
    public function __construct(public readonly Response $response)
    {
        parent::__construct('HTTP response ready');
    }
}
