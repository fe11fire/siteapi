<?php

namespace SiteApi\Root\Helpers;

/** SQL structure must be trusted application code; values belong in positional bindings. */
final class RawCondition
{
    /**
     * Создаёт явное SQL-условие с позиционными параметрами.
     *
     * SQL-фрагмент должен быть доверенным; синтаксис SQL и число плейсхолдеров не проверяются.
     * @param list<scalar|null> $bindings Значения для ? в порядке появления.
     * @throws \InvalidArgumentException Пустой SQL или bindings не является списком.
     */
    public function __construct(public readonly string $sql, public readonly array $bindings = [])
    {
        if (trim($sql) === '' || !array_is_list($bindings)) {
            throw new \InvalidArgumentException('RawCondition requires SQL and a list of positional bindings');
        }
    }
}
