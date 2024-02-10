<?php

namespace SiteApi\Root\Messenger;

use SiteApi\Root\Helpers\LogHelper;
use SiteApi\Root\Messenger\Recipient;

abstract class ClientContract
{
    const SERVICE_TELEGRAM = 'telegram';
    const SERVICES = [self::SERVICE_TELEGRAM];

    protected string $service_name;

    function __construct(string $service_name, array $params)
    {
        if (!in_array($service_name, self::SERVICES)) {
            LogHelper::log(`{$service_name} not in array SERVICES`, ['service_name' => $service_name, 'services' => json_encode(self::SERVICES)], 'errors/');
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
