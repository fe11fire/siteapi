<?php

namespace SiteApi\Root\Messenger;

interface ClientContract
{
    /**
     * Возвращает единственный канал, обслуживаемый клиентом.
     */
    public function getChannel(): Channel;
    /**
     * Отправляет сообщение получателю своего канала и возвращает итоги доставки.
     *
     * Невалидные входные данные могут вызвать исключение; операционные отказы
     * штатные клиенты отражают в SendResult. Автоматический повтор контрактом не гарантируется.
     */
    public function send(Message $message, Recipient $recipient): SendResult;
}
