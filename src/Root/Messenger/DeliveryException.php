<?php

namespace SiteApi\Root\Messenger;

/** Safe category only: never contains provider payloads or credentials. */
final class DeliveryException extends \RuntimeException
{
    public function __construct(public readonly string $reason, int $code = 0)
    {
        parent::__construct($reason, $code);
    }
}
