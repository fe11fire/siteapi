<?php

namespace SiteApi\Root\Http;

/** @internal Control flow handled by App; must not be treated as an application error. */
final class ResponseReady extends \RuntimeException
{
    public function __construct(public readonly Response $response)
    {
        parent::__construct('HTTP response ready');
    }
}
