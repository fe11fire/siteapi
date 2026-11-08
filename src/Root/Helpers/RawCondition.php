<?php

namespace SiteApi\Root\Helpers;

/** SQL structure must be trusted application code; values belong in positional bindings. */
final class RawCondition
{
    public function __construct(public readonly string $sql, public readonly array $bindings = [])
    {
        if (trim($sql) === '' || !array_is_list($bindings)) {
            throw new \InvalidArgumentException('RawCondition requires SQL and a list of positional bindings');
        }
    }
}
