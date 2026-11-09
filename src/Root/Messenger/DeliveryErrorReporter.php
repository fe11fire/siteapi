<?php

namespace SiteApi\Root\Messenger;

interface DeliveryErrorReporter
{
    /**
     * Регистрирует отказ части доставки без изменения результата.
     *
     * Реализация должна защищать секреты и не выбрасывать ошибки журналирования:
     * вызывающие клиенты отдельно не перехватывают исключения reporter.
     */
    public function report(SendResult $result, string $part, string $reason, \Throwable $error): void;
}
