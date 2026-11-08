<?php

namespace SiteApi\Root\Messenger;

final class SendResult
{
    public readonly string $operationId;
    /** @var array<int, array{part: string, success: bool, provider_id: ?string, reason?: string}> */
    private array $parts = [];

    public function __construct(public readonly Channel $channel, public readonly ?string $reference = null)
    {
        $this->operationId = bin2hex(random_bytes(12));
    }

    public function accepted(string $part, ?string $providerId = null): void
    {
        $this->parts[] = ['part' => $part, 'success' => true, 'provider_id' => $providerId];
    }

    public function failed(string $part, string $reason): void
    {
        $this->parts[] = ['part' => $part, 'success' => false, 'provider_id' => null, 'reason' => $reason];
    }

    public function getParts(): array { return $this->parts; }

    public function getStatus(): string
    {
        $successes = count(array_filter($this->parts, fn(array $part) => $part['success']));
        if (!$successes) return 'failed';
        return $successes === count($this->parts) ? 'success' : 'partial';
    }
}
