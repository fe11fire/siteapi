<?php

namespace SiteApi\Root\Messenger;

final class Sender
{
    private array $clients = [];

    /**
     * Регистрирует не более одного клиента на канал, не выполняя доставку.
     * @throws \InvalidArgumentException Клиенты дублируют один канал.
     */
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

    /**
     * Выбирает клиент по каналу получателя и повторно проверяет вложения перед отправкой.
     *
     * Автоматических повторов нет: после таймаута результат доставки может быть неизвестен.
     * @return SendResult Итоги попыток, а не гарантия прочтения сообщения адресатом.
     * @throws \LogicException Клиент канала не зарегистрирован.
     * @throws \InvalidArgumentException Некорректное вложение/сообщение для канала.
     * @throws \Throwable Исключение пользовательской реализации клиента.
     */
    public function send(Message $message, Recipient $recipient): SendResult
    {
        $client = $this->clients[$recipient->channel->value] ?? null;
        if (!$client) throw new \LogicException('Channel is not configured: ' . $recipient->channel->value);
        foreach ($message->attachments as $attachment) $attachment->validate();
        return $client->send($message, $recipient);
    }
}
