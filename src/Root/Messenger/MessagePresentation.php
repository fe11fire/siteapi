<?php

namespace SiteApi\Root\Messenger;

/** Shared presentation rules, independent of delivery and message validation. */
final class MessagePresentation
{
    /**
     * Возвращает тему либо подпись Вложения/Полезные ссылки по наличию вложений.
     *
     * HTML-экранирование результата выполняет вызывающий код.
     */
    public static function heading(Message $message): string
    {
        return trim($message->subject) !== '' ? $message->subject : ($message->attachments ? 'Вложения' : 'Полезные ссылки');
    }

    /**
     * Экранирует текст UTF-8 для HTML, заменяя некорректные последовательности.
     *
     * Не является проверкой URL, CSS или JavaScript.
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
