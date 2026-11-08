<?php

namespace SiteApi\Root\Messenger;

/** Shared presentation rules, independent of delivery and message validation. */
final class MessagePresentation
{
    public static function heading(Message $message): string
    {
        return trim($message->subject) !== '' ? $message->subject : ($message->attachments ? 'Вложения' : 'Полезные ссылки');
    }

    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
