<?php

namespace SiteApi\Root\Clients\Telegram\Templates;

use SiteApi\Root\Messenger\ClientContract;
use SiteApi\Root\Messenger\TemplateContract;


final class MessageWithInlineExample extends TemplateContract
{
    function makeMessage(ClientContract $client): array
    {
        switch ($client->getServiceName()) {
            case ClientContract::SERVICE_TELEGRAM:
                return [implode(chr(10), $this->data)];
                break;
            default:
                # code...
                break;
        }
        return [];
    }

    public function makeReplyMarkup(ClientContract $client): array
    {
        $keyboard = [
            'inline_keyboard' => [[
                [
                    "text" => 'Назад',
                    "web_app" => ['url' => 'https://'],
                ]
            ]],
        ];

        return ['reply_markup' => json_encode($keyboard)];
    }
}
