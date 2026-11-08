<?php

namespace SiteApi\Root\Clients\CURL;

use SiteApi\Root\Helpers\LogHelper;


class Curl
{
    protected $curl;
    protected int $http_code;
    protected mixed $result = null;
    private ?string $error = null;

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
        $this->curl_payload($user_payload);
        $this->log_folder = $log_folder;
    }

    public function exec(): bool
    {
        $this->result = null;
        $this->error = null;
        $this->http_code = 0;
        $body = curl_exec($this->curl);
        $this->http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        $errno = curl_errno($this->curl);
        if ($body === false) {
            $this->error = $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'connection_failed';
        } else {
            $validJson = true;
            if ($this->http_code !== 204 || $body !== '') {
                try {
                    $this->result = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $validJson = false;
                }
            }
            if ($this->http_code < 200 || $this->http_code >= 300) {
                $this->error = 'http_error';
            } elseif (!$validJson) {
                $this->error = 'invalid_json';
            }
        }
        // Do not log URLs, headers, request/response bodies or raw transport errors.
        $context = ['http_code' => $this->http_code, 'error' => $this->error, 'curl_errno' => $errno];
        try {
            if ($this->error !== null) {
                LogHelper::error('CURL request failed', $context);
            } else {
                LogHelper::log('CURL request succeeded', $context, $this->log_folder);
            }
        } catch (\Throwable) {
            error_log('CURL request result: ' . json_encode($context));
        }
        return $this->error === null;
    }

    public function get_error(): ?string
    {
        return $this->error;
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
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
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
