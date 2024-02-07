<?php

namespace SiteApi\Root\Messenger;

abstract class ClientContract
{
    abstract class setServiceName():void; SERVICE_NAME;
    const SERVICE_TELEGRAM = 'telegram';

    function getServiceName(): string
    {
        return static::SERVICE_NAME;
    }

    abstract function send(Message $message): bool;
}
