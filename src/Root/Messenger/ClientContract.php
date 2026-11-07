<?php

namespace SiteApi\Root\Messenger;

use InvalidArgumentException;

abstract class ClientContract
{
    const SERVICE_TELEGRAM = 'telegram';
    const SERVICES = [self::SERVICE_TELEGRAM];

    protected string $service_name;

    function __construct(string $service_name, array $params)
    {
        if (!in_array($service_name, self::SERVICES, true)) {
            throw new InvalidArgumentException("Unsupported service: {$service_name}");
        }
        $this->service_name = $service_name;
        $this->init($params);
    }

    function getServiceName(): string
    {
        return $this->service_name;
    }

    protected abstract function init(array $params): void;

    abstract function send(Message $message, Recipient $recipient): bool;
}
