<?php

namespace SiteApi\Root\Messenger;

final class Link
{
    public function __construct(public readonly string $label, public readonly string $url)
    {
        if (trim($label) === '' || !preg_match('//u', $label) || !filter_var($url, FILTER_VALIDATE_URL)
            || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Link requires a label and HTTP(S) URL');
        }
    }
}
