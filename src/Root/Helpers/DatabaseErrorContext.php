<?php

namespace SiteApi\Root\Helpers;

use PDOException;
use Throwable;

/** Safe metadata only: PDO messages and errorInfo[2] may contain query values. */
final class DatabaseErrorContext
{
    /**
     * Ищет PDOException в цепочке исключений и возвращает безопасные метаданные.
     *
     * Не включает сообщения PDO, SQL и текст ошибки драйвера, где могут быть секреты.
     * @return array{exception_type: class-string, sqlstate: ?string, driver_code: ?int}|null
     */
    public static function from(Throwable $error): ?array
    {
        do {
            if ($error instanceof PDOException) {
                $info = $error->errorInfo ?? [];
                $state = $info[0] ?? $error->getCode();
                $driver = $info[1] ?? null;
                return [
                    'exception_type' => $error::class,
                    'sqlstate' => is_string($state) && preg_match('/\A[A-Z0-9]{5}\z/', $state) ? $state : null,
                    'driver_code' => is_int($driver) ? $driver : null,
                ];
            }
            $error = $error->getPrevious();
        } while ($error !== null);
        return null;
    }
}
