<?php

namespace SiteApi\Root\Messenger;

/** Safe category only: never contains provider payloads or credentials. */
final class DeliveryException extends \RuntimeException
{
    /**
     * Создаёт исключение с безопасной категорией отказа и числовым кодом провайдера.
     *
     * Не включайте токены, адреса, содержимое ответа провайдера или сообщения в reason.
     */
    public function __construct(public readonly string $reason, int $code = 0)
    {
        parent::__construct($reason, $code);
    }
}
