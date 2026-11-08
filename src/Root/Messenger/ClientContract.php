<?php

namespace SiteApi\Root\Messenger;

interface ClientContract
{
    public function getChannel(): Channel;
    public function send(Message $message, Recipient $recipient): SendResult;
}
