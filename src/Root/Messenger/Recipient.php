<?php

namespace SiteApi\Root\Messenger;

use InvalidArgumentException;
use RuntimeException;

class Recipient
{
    const FIELD_MESSAGE = 'message';
    const FIELD_TITLE = 'title';
    protected array $addresses = [];

    function __construct(array $addresses)
    {
        foreach ($addresses as $service => $address) {
            if (!in_array($service, ClientContract::SERVICES, true)) {
                throw new InvalidArgumentException("Unsupported service: {$service}");
            }
            $this->addresses[$service] = $address;
        }
    }

    function getAddress(ClientContract $client): string
    {
        $service = $client->getServiceName();
        if (!isset($this->addresses[$service])) {
            throw new RuntimeException("Recipient address is missing for service: {$service}");
        }
        return $this->addresses[$service];
    }
}
