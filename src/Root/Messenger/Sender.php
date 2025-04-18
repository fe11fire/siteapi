<?php

namespace SiteApi\Root\Messenger;

use SiteApi\Root\Helpers\LogHelper;

class Sender
{
    /**
     * @param ClientContract[] $clients
     */
    public static function send(Message $message, array $clients, Recipient $recipient)
    {
        foreach ($clients as $client) {
            $client->send($message, $recipient);
        }
    }
}
