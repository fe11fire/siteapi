<?php
namespace SiteApi\Root\Clients\Telegram;


use Exception;
use Telegram\Bot\Api;


class Sender
{
    // private string $id_telegram;
    private Api $telegram;

    function __construct(int $token)
    {
        $this->telegram = new Api($token);
        // throw new Exception('No bot with id = ' . $id_Telegram_Bot);
    }

    public function send(Message $message): bool
    {
        LogHelper::log('TelegramSender send', ['message' => $message->get($this)]);
        try {
            $result = $this->telegram->sendMessage(
                [
                    'chat_id' => $this->id_telegram,
                    'text'    => $message->get($this),
                    'parse_mode' => 'HTML',
                ]
                    + $message->get_Reply_Markup(),
            );
        } catch (Exception $e) {
            LogHelper::log(__METHOD__, ['error' => $e->getMessage()], 'errors/');
            return false;
        }
        return true;
    }

    public static function code(string $text): string
    {
        return '<code>' . $text . '</code>';
    }

    public static function pre(string $text): string
    {
        return '<pre>' . $text . '</pre>';
    }

    public static function i(string $text): string
    {
        return '<i>' . $text . '</i>';
    }

    public static function b(string $text): string
    {
        return '<b>' . $text . '</b>';
    }

    public static function u(string $text): string
    {
        return '<u>' . $text . '</u>';
    }

    public static function a(string $text, string $link): string
    {
        return '<a href="' . $link . '">' . $text . '</a>';
    }

    public static function spoiler_start(): string
    {
        return '<span class="tg-spoiler">';
    }

    public static function spoiler_end(): string
    {
        return '</span>';
    }
}
