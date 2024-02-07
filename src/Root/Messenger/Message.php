<?php

namespace SiteApi\Root\Messenger;

class Message
{
    private TemplateContract $template;

    public function __construct(TemplateContract $template, array $data, ?array $params)
    {
        $template->init($data, isset($params) ? $params : null);
    }

    public function get(ClientContract $client)
    {
        return $this->template->makeMessage($client);
    }
}
