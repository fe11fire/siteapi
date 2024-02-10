<?php

namespace SiteApi\Root\Clients\Telegram\Templates;

use SiteApi\Root\Messenger\ClientContract;
use SiteApi\Root\Messenger\TemplateContract;

final class SimpleMessage extends TemplateContract
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
}
