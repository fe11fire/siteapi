<?php

namespace SiteApi\Root\Messenger;

use SiteApi\Root\Helpers\LogHelper;

class Sender
{
    /**
     * @param ClientContract[] $clients
     */
    public static function send(Message $message, array $clients)
    {
        foreach ($clients as $client) {
            $client->send($message);
        }
    }
}
