<?php

namespace SiteApi\Root\Messenger;

use Exception;
use SiteApi\Root\Helpers\LogHelper;

class Message
{
    const PARAM_ATTACHMENTS = 'attachments';
    private TemplateContract $template;
    private array $attachments = [];

    public function __construct(array $data, array $params = [], string $templateContractClass, string $replyMarkupClass = null)
    {
        if (!is_subclass_of($templateContractClass, TemplateContract::class)) {
            throw new Exception('templateContractClass is not subclass of TemplateContract');
        }
        if ((isset($params[self::PARAM_ATTACHMENTS])) && (is_array($params[self::PARAM_ATTACHMENTS]))) {
            $this->attachments = $params[self::PARAM_ATTACHMENTS];
        }
        $this->template = new $templateContractClass($data, $params, $replyMarkupClass);
    }

    public function getMessage(ClientContract $client)
    {
        return $this->template->makeMessage($client);
    }

    public function hasAttachments(): bool
    {
        return count($this->attachments) > 0;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getReplyMarkup(ClientContract $client)
    {
        return $this->template->makeReplyMarkup($client);
    }
}
