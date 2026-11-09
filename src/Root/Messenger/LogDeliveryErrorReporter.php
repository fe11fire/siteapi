<?php

namespace SiteApi\Root\Messenger;

use SiteApi\Root\Helpers\LogHelper;
use Throwable;

final class LogDeliveryErrorReporter implements DeliveryErrorReporter
{
    /**
     * Записывает безопасные метаданные отказа доставки, без сообщения и адреса получателя.
     *
     * Отказ логгера переводит в error_log и не меняет результат доставки.
     */
    public function report(SendResult $result, string $part, string $reason, Throwable $error): void
    {
        $context = ['operation_id' => $result->operationId, 'channel' => $result->channel->value,
            'part' => $part, 'reason' => $reason, 'exception_type' => $error::class, 'code' => $error->getCode()];
        try {
            LogHelper::error('Message delivery failed', $context);
        } catch (Throwable) {
            error_log('Message delivery failed: ' . json_encode($context));
        }
    }
}
