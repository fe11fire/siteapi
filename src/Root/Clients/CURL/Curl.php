<?php

namespace SiteApi\Root\Clients\CURL;

use SiteApi\Root\Helpers\LogHelper;


class Curl
{
    protected $curl;
    protected int $http_code;
    protected mixed $result;
    private $user_payload;

    private string $log_folder = 'curl/';

    public function __construct(
        string $url,
        array $user_header,
        string $customrequest,
        array $user_payload,
        string $log_folder = 'curl/',
    ) {
        $this->http_code = -1;

        $this->curl = curl_init();
        $this->curl_base($url);
        $this->curl_header($user_header);
        $this->curl_customrequest($customrequest);
        $this->user_payload = $user_payload;
        $this->curl_payload($user_payload);
        $this->log_folder = $log_folder;
    }

    public function exec()
    {
        LogHelper::log('CURL exec', ['curl_getinfo' => curl_getinfo($this->curl), 'user_payload' => $this->user_payload], $this->log_folder);
        $this->result = curl_exec($this->curl);
        if ($this->result === false) {
            LogHelper::log('CURL exec failed !!!', [], $this->log_folder);
            return false;
        }
        $this->result = json_decode($this->result);
        $this->http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        LogHelper::log('CURL exec success', ['result' => $this->result, 'code' => $this->http_code], $this->log_folder);
        return true;
    }

    public function clear_token($token)
    {
        $token = str_replace('"', "", $token);
        return trim($token);
    }

    public function get_result(): mixed
    {
        return $this->result;
    }

    public function get_http_code(): int
    {
        return $this->http_code;
    }

    private function curl_base($url)
    {
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->curl, CURLOPT_VERBOSE, 0);
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT_MS, 5000);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 10);
    }

    private function curl_header($user_header = [])
    {
        curl_setopt($this->curl, CURLOPT_HTTPHEADER,  array_merge(['Content-Type:application/json'], $user_header));
    }

    private function curl_customrequest($customrequest = "POST")
    {
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $customrequest);
    }

    private function curl_payload($payload = [])
    {
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    public static function ping(string $url): bool
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($curl);
        curl_close($curl);

        return boolval($response);
    }
}
