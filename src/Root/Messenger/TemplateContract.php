<?php

namespace SiteApi\Root\Messenger;

abstract class TemplateContract
{
    const FIELD_MESSAGE = 'message';
    const FIELD_TITLE = 'title';

    abstract function init(array $data, ?array $params): void;
    abstract function makeMessage(ClientContract $client): array;
}
