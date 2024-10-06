<?php

namespace SiteApi\Root\Messenger;

use SiteApi\Root\Helpers\LogHelper;

abstract class TemplateContract
{
    const FIELD_MESSAGE = 'message';
    const FIELD_TITLE = 'title';

    protected array $data;
    protected array $params = [];
    protected TemplateContract $reply_markup;

    function __construct(array $data, array $params = [], string $reply_markup_class = null)
    {
        $this->data = $data;
        $this->params = $params;
        if (isset($reply_markup)) {
            $this->reply_markup = new $reply_markup_class($this->data, $this->params);
        }
    }

    abstract function makeMessage(ClientContract $client): array;

    public function makeReplyMarkup(ClientContract $client): array
    {
        if (!isset($this->reply_markup)) {
            LogHelper::log('reply_markup is not isset', [], 'errors/');
            return [];
        }
        return $this->reply_markup->makeMessage($client);
    }

    public function tg_code(string $text): string
    {
        return '<code>' . $text . '</code>';
    }

    public function tg_pre(string $text): string
    {
        return '<pre>' . $text . '</pre>';
    }

    public function tg_i(string $text): string
    {
        return '<i>' . $text . '</i>';
    }

    public function tg_b(string $text): string
    {
        return '<b>' . $text . '</b>';
    }

    public function tg_u(string $text): string
    {
        return '<u>' . $text . '</u>';
    }

    public function tg_a(string $text, string $link): string
    {
        return '<a href="' . $link . '">' . $text . '</a>';
    }

    public function tg_spoiler_start(): string
    {
        return '<span class="tg-spoiler">';
    }

    public function tg_spoiler_end(): string
    {
        return '</span>';
    }
}
