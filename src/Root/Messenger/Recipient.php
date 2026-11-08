<?php

namespace SiteApi\Root\Messenger;

final class Recipient
{
    public readonly Channel $channel;

    public function __construct(Channel|string $channel, public readonly string $address)
    {
        $this->channel = $channel instanceof Channel ? $channel : (Channel::tryFrom($channel) ?? throw new \InvalidArgumentException('Unsupported channel'));
        $valid = $this->channel === Channel::EMAIL
            ? filter_var($address, FILTER_VALIDATE_EMAIL)
            : preg_match('/^(?:-?[1-9][0-9]*|@[a-zA-Z][a-zA-Z0-9_]{4,})$/D', $address);
        if (!$valid) throw new \InvalidArgumentException('Invalid recipient address');
    }
}
