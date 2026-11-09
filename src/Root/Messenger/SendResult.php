<?php

namespace SiteApi\Root\Messenger;

final class SendResult
{
    public readonly string $operationId;
    /** @var array<int, array{part: string, success: bool, provider_id: ?string, reason?: string}> */
    private array $parts = [];

    /**
     * Создаёт результат доставки со случайным operationId и прикладным reference.
     *
     * До регистрации попыток getStatus() возвращает failed.
     * @throws \Throwable Ошибка источника случайности.
     */
    public function __construct(public readonly Channel $channel, public readonly ?string $reference = null)
    {
        $this->operationId = bin2hex(random_bytes(12));
    }

    /**
     * Добавляет успешную попытку доставки части сообщения.
     *
     * part не обязан быть уникальным; providerId подтверждает принятие провайдером, не прочтение.
     */
    public function accepted(string $part, ?string $providerId = null): void
    {
        $this->parts[] = ['part' => $part, 'success' => true, 'provider_id' => $providerId];
    }

    /**
     * Добавляет неудачную попытку части сообщения с безопасной категорией reason.
     *
     * Секреты и сырые ошибки провайдера в reason помещать не следует.
     */
    public function failed(string $part, string $reason): void
    {
        $this->parts[] = ['part' => $part, 'success' => false, 'provider_id' => null, 'reason' => $reason];
    }

    /**
     * Возвращает зарегистрированные попытки в порядке добавления.
     * @return list<array{part: string, success: bool, provider_id: ?string, reason?: string}>
     */
    public function getParts(): array { return $this->parts; }

    /**
     * Возвращает success при всех успешных попытках, partial при смеси и failed без успехов.
     *
     * Отсутствие попыток тоже считается failed.
     */
    public function getStatus(): string
    {
        $successes = count(array_filter($this->parts, fn(array $part) => $part['success']));
        if (!$successes) return 'failed';
        return $successes === count($this->parts) ? 'success' : 'partial';
    }
}
