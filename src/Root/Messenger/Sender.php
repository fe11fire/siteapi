<?php

namespace SiteApi\Root\Messenger;

final class Sender
{
    private array $clients = [];

    public function __construct(ClientContract ...$clients)
    {
        foreach ($clients as $client) {
            $channel = $client->getChannel()->value;
            if (isset($this->clients[$channel])) {
                throw new \InvalidArgumentException('Unsupported or duplicate channel');
            }
            $this->clients[$channel] = $client;
        }
    }

    public function send(Message $message, Recipient $recipient): SendResult
    {
        $client = $this->clients[$recipient->channel->value] ?? null;
        if (!$client) throw new \LogicException('Channel is not configured: ' . $recipient->channel->value);
        foreach ($message->attachments as $attachment) $attachment->validate();
        return $client->send($message, $recipient);
    }
}
