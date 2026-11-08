<?php

namespace SiteApi\Root\Messenger;

interface DeliveryErrorReporter
{
    public function report(SendResult $result, string $part, string $reason, \Throwable $error): void;
}
