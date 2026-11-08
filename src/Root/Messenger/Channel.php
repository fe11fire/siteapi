<?php

namespace SiteApi\Root\Messenger;

enum Channel: string
{
    case TELEGRAM = 'telegram';
    case EMAIL = 'email';
}
