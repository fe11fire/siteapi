<?php

use Exception;
use SiteApi\Root\Helpers\LogHelper;

class TelegramRequest
{
    private $request;
    private string $type = '';
    private string $chat_id = '';

    function __construct($request)
    {
        $this->request = $request;
        $this->define_Type();
        $this->define_Chat_ID();
    }

    public function get_Type(): string
    {
        return $this->type;
    }

    public function get_Chat_ID(): string
    {
        return $this->chat_id;
    }

    public function get_Command(): string
    {
        if (isset($this->request["callback_query"])) {
            try {
                return $this->get_Callback_Data('C');
            } catch (Exception $e) {
                LogHelper::log('TelegramRequest get_Command', ['error' => $e->getMessage(), 'callback_data' => $this->request["callback_query"]["data"]]);
                return '';
            }
        }

        switch ($this->get_Type()) {
            case 'text':
                return $this->request["message"]["text"];
                break;
            case 'sticker':
                return $this->request["message"]["sticker"]["set_name"];
                break;

            default:
                # code...
                break;
        }
    }

    public function get_Callback_Data($column)
    {
        if (!$this->request["callback_query"]) {
            throw new Exception('Not callback_query');
        }

        if (!$this->request["callback_query"]["data"]) {
            throw new Exception('No data in callback_query');
        }

        $data = json_decode($this->request["callback_query"]["data"], true);

        if (!isset($data[$column])) {
            throw new Exception('No column "' . $column . '" in callback_query');
        }

        return $data[$column];
    }

    public function set_Callback_Data($column, $value): void
    {
        $data[$column] = $value;
    }

    public function get_Message_ID(): string
    {
        if (!$this->request["callback_query"]) {
            throw new Exception('Not callback_query & message_id');
        }

        return $this->request["callback_query"]["message"]["message_id"];
    }

    public function get_Username(): string
    {
        return $this->request["message"]["chat"]["username"];
    }

    private function define_Chat_ID(): void
    {
        if (isset($this->request["callback_query"])) {
            $this->chat_id = $this->request["callback_query"]['message']['chat']['id'];
        } else {
            $this->chat_id = $this->request["message"]["chat"]["id"];
        }
    }

    private function define_Type(): void
    {
        $this->type = 'text';
        if (isset($this->request["callback_query"])) {
            $this->type = 'text';
            return;
        }

        if ($this->request["message"]["text"]) {
            $this->type = 'text';
            return;
        }

        if ($this->request["message"]["photo"][0]["file_id"]) {
            $this->type = 'image';
            return;
        }

        if ($this->request["message"]["sticker"]["file_id"]) {
            $this->type = 'sticker';
            return;
        }


        // $audio = $result["message"]["audio"]["file_id"];
        // $video = $result["message"]["video"]["file_id"];
        // $voice = $result["message"]["voice"]["file_id"];
        // $contact = $result["message"]["contact"]["user_id"];
        // $location = $result["message"]["location"]["longitude"];
        // $venue = $result["message"]["venue"]["title"];
        // $document = $result["message"]["document"]["file_id"];
        // $game = $result["message"]["game"]["title"];
    }
}
