<?php

namespace SiteApi\Root\Clients\Telegram;

use SiteApi\Root\Messenger\{ClientContract, Message, Recipient, SendResult, Channel, MessagePresentation, DeliveryException, DeliveryErrorReporter, LogDeliveryErrorReporter};

class Client implements ClientContract
{
    /**
     * Создаёт Telegram-клиент с явным токеном и регистратором отказов.
     *
     * Сеть не вызывается; проверяется формат токена, но не его действительность.
     * @throws \InvalidArgumentException Неверный формат токена.
     * @throws \LogicException Недоступно расширение cURL.
     */
    public function __construct(private readonly string $token, private readonly DeliveryErrorReporter $reporter = new LogDeliveryErrorReporter())
    {
        if (!preg_match('/^[0-9]+:[a-zA-Z0-9_-]+$/D', $token)) throw new \InvalidArgumentException('Invalid Telegram token');
        if (!extension_loaded('curl')) throw new \LogicException('Telegram requires ext-curl');
    }

    /**
     * Возвращает канал TELEGRAM.
     */
    public function getChannel(): Channel { return Channel::TELEGRAM; }

    /**
     * Отправляет текст, затем документы отдельными запросами Telegram.
     *
     * При отказе текста документы не отправляются; отказ документа не останавливает остальные.
     * Автоматического разбиения и повторов нет. Ограничения: 4096 UTF-16 единиц текста,
     * 100 действий, подпись кнопки до 256 байт, файл до 50 000 000 байт.
     * Отказы транспорта отражаются в SendResult; reporter не должен выбрасывать исключения.
     * @throws \InvalidArgumentException Чужой канал, неверное вложение или превышение лимитов.
     * @throws \JsonException Невозможно закодировать кнопки до начала доставки.
     */
    public function send(Message $message, Recipient $recipient): SendResult
    {
        if ($recipient->channel !== $this->getChannel()) throw new \InvalidArgumentException('Wrong recipient channel');
        $heading = trim($message->subject) !== '' ? $message->subject : (trim($message->text) === '' ? MessagePresentation::heading($message) : '');
        $plain = ($heading !== '' ? $heading . "\n\n" : '') . $message->text;
        // Count UTF-16 units conservatively, matching Telegram entity offsets.
        preg_match_all('/./us', $plain, $characters);
        $length = array_sum(array_map(fn($char) => strlen($char) === 4 ? 2 : 1, $characters[0]));
        if ($length > 4096 || count($message->actions) > 100) throw new \InvalidArgumentException('Telegram message exceeds supported limits');
        foreach ($message->actions as $link) {
            if (strlen($link->label) > 256) throw new \InvalidArgumentException('Telegram button label is too long');
        }
        foreach ($message->attachments as $file) {
            $file->validate();
            if (filesize($file->path) > 50 * 1000 * 1000) throw new \InvalidArgumentException('Telegram attachment exceeds 50 MB');
        }
        $payload = ['chat_id' => $recipient->address, 'parse_mode' => 'HTML',
            'text' => ($heading !== '' ? '<b>' . MessagePresentation::escape($heading) . "</b>\n\n" : '') . MessagePresentation::escape($message->text)];
        if ($message->actions) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => array_map(
                fn($link) => [['text' => $link->label, 'url' => $link->url]], array_values($message->actions)
            )], JSON_THROW_ON_ERROR);
        }
        $result = new SendResult($this->getChannel(), $message->reference);
        try {
            $response = $this->request('sendMessage', $payload);
            $result->accepted('text', (string) $response['message_id']);
        } catch (\Throwable $error) {
            $reason = $error instanceof DeliveryException ? $error->reason : 'delivery_failed';
            $result->failed('text', $reason);
            $this->reporter->report($result, 'text', $reason, $error);
            // Documents depend on the introductory message. Do not send them out of context.
            return $result;
        }
        foreach (array_values($message->attachments) as $index => $file) {
            try {
                $response = $this->request('sendDocument', ['chat_id' => $recipient->address,
                    'document' => new \CURLFile($file->path, $file->mimeType, $file->name)]);
                $result->accepted('attachment:' . $index, (string) $response['message_id']);
            } catch (\Throwable $error) {
                $reason = $error instanceof DeliveryException ? $error->reason : 'delivery_failed';
                $result->failed('attachment:' . $index, $reason);
                $this->reporter->report($result, 'attachment:' . $index, $reason, $error);
            }
        }
        return $result;
    }

    /** Protected transport seam permits offline testing without sending messages. */
    protected function request(string $method, array $payload): array
    {
        $curl = curl_init('https://api.telegram.org/bot' . $this->token . '/' . $method);
        try {
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 30]);
            $body = curl_exec($curl);
            if ($body === false) throw new DeliveryException(curl_errno($curl) === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'transport_failed', curl_errno($curl));
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (empty($data['ok']) || !isset($data['result']['message_id'])) {
                throw new DeliveryException('provider_rejected', (int) ($data['error_code'] ?? 0));
            }
            return $data['result'];
        } finally {
            curl_close($curl);
        }
    }
}
