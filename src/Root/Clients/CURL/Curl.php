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

    /**
     * Настраивает JSON HTTP-запрос, не выполняя его до exec().
     *
     * Тело отправляется даже при пользовательском методе GET. Таймаут соединения 5 секунд,
     * общий 10 секунд. URL должен быть доверенным: ограничения внутренних адресов нет.
     * @param list<string> $user_header Заголовки в формате Name: value.
     * @param array $user_payload Данные JSON-тела.
     * @param string $log_folder Доверенный подкаталог журнала успешных запросов.
     * @throws \JsonException Payload невозможно закодировать.
     */
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

    /**
     * Выполняет запрос и сохраняет статус, декодированный результат и категорию ошибки.
     *
     * Успех: HTTP 2xx и корректный JSON либо пустой ответ 204. JSON-объекты становятся stdClass.
     * Сетевые/HTTP/JSON-ошибки возвращают false, подробности доступны через get_error().
     * JSON-тело ошибочного HTTP-ответа также может быть доступно через get_result().
     * Повторов нет; отказ логгера не меняет результат запроса.
     */
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

    /**
     * Возвращает null либо timeout, connection_failed, http_error, invalid_json.
     *
     * До первого exec() также null: это не свидетельство выполненного успешного запроса.
     */
    public function get_error(): ?string
    {
        return $this->error;
    }

    /**
     * Удаляет все двойные кавычки и крайние пробельные символы из строки.
     *
     * Не проверяет формат токена и не выполняет криптографическую обработку.
     * @param string $token
     * @return string
     */
    public function clear_token($token)
    {
        $token = str_replace('"', "", $token);
        return trim($token);
    }

    /**
     * Возвращает декодированный JSON последнего запроса, включая JSON ошибочного HTTP-ответа.
     *
     * null также означает отсутствие результата, пустой 204, ошибку JSON либо JSON null;
     * для различения используйте exec(), get_error() и get_http_code().
     */
    public function get_result(): mixed
    {
        return $this->result;
    }

    /**
     * Возвращает HTTP-статус: -1 до exec(), 0 при отсутствии HTTP-ответа.
     */
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

    /**
     * Проверяет получение непустого HEAD-ответа, а не успешность HTTP-статуса.
     *
     * Ответы 404/500 с заголовками также могут дать true. URL должен быть доверенным.
     * Ограничение: 10 секунд заданы только на соединение, общий таймаут отсутствует;
     * сервер, принявший соединение и задерживающий ответ, может надолго заблокировать вызов.
     */
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
