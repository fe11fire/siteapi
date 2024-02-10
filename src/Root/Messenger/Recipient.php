<?php

namespace SiteApi\Root\Messenger;

use Exception;
use SiteApi\Root\Helpers\LogHelper;

class Recipient
{
    const FIELD_MESSAGE = 'message';
    const FIELD_TITLE = 'title';
    protected array $addresses = [];

    function __construct(array $addresses)
    {
        foreach ($addresses as $service => $address) {
            if (!in_array($service, ClientContract::SERVICES)) {
                LogHelper::log(`{$service} not in array SERVICES`, ['address' => $address, 'service_name' => $service, 'services' => json_encode(ClientContract::SERVICES)], 'errors/');
            } else {
                $this->addresses[$service] = $address;
            }
        }
    }

    function getAddress(ClientContract $client): string
    {
        if (!isset($this->addresses[$client->getServiceName()])) {
            LogHelper::log(`{$client} not in array addresses`, ['address' => $client, 'services' => json_encode($this->addresses)], 'errors/');
            throw new Exception(`{$client} not in array addresses`);
        }
        return $this->addresses[$client->getServiceName()];
    }
}
