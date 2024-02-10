<?php

namespace SiteApi\Root\Clients\Telegram;


use Exception;

use Telegram\Bot\Api;
use SiteApi\Root\Helpers\LogHelper;
use SiteApi\Root\Messenger\Message;
use SiteApi\Root\Messenger\Recipient;
use Telegram\Bot\FileUpload\InputFile;
use SiteApi\Root\Messenger\ClientContract;

class Client extends ClientContract
{
    private Api $telegram;

    protected function init(array $params): void
    {
        if (!isset($params['token'])) {
            LogHelper::log(__METHOD__ . ' token is not isset', ['params' => $params], 'errors/');
            throw new Exception('Ошибка при отправке сообщения');
        }
        $this->telegram = new Api($params['token']);
    }

    public function send(Message $message, Recipient $recipient): bool
    {
        if ($message->hasAttachments()) {
            foreach ($message->getAttachments() as $attachment) {
                $file = InputFile::create($attachment['file'], $attachment['filename']);
                $response = $this->telegram->sendDocument([
                    'chat_id' => $recipient->getAddress($this),
                    'document' => $file,
                ]);
            }
        }

        LogHelper::log('TelegramSender send', ['message' => $message->getMessage($this)]);
        try {
            $result = $this->telegram->sendMessage(
                [
                    'chat_id' => $recipient->getAddress($this),
                    'text'    => $message->getMessage($this)[0],
                    'parse_mode' => 'HTML',
                ] + $message->getReplyMarkup($this),
            );
        } catch (Exception $e) {
            LogHelper::log(__METHOD__, ['error' => $e->getMessage()], 'errors/');
            return false;
        }
        return true;
    }
}
