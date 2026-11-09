<?php

namespace SiteApi\Root\Http;

/** Immutable JSON response. Call throwSend() inside App::run() to finish the request. */
final class Response
{
    private function __construct(
        public readonly string $body,
        public readonly int $httpStatus,
        public readonly array $headers,
    ) {}

    public static function json(mixed $data, int $httpStatus = 200, array $headers = []): self
    {
        if ($httpStatus < 200 || $httpStatus > 599 || in_array($httpStatus, [204, 205, 304], true)) {
            throw new \InvalidArgumentException('JSON responses require a status that permits a body');
        }
        $validated = ['Content-Type' => 'application/json; charset=utf-8'];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !preg_match('/\A[A-Za-z0-9!#$%&\x27*+.^_`|~-]+\z/', $name)
                || !is_string($value) || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                throw new \InvalidArgumentException('Invalid response header');
            }
            if (in_array(strtolower($name), ['content-type', 'content-length', 'transfer-encoding'], true)) {
                throw new \InvalidArgumentException('Response controls its content headers');
            }
            $validated[$name] = $value;
        }
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new self($body, $httpStatus, $validated);
    }

    /** code is an application value, independent of the HTTP status. */
    public static function ok(int $code = 0, mixed $data = '', int $httpStatus = 200, array $headers = []): self
    {
        return self::json(['status' => true, 'code' => $code, 'data' => $data], $httpStatus, $headers);
    }

    /** data is public response data, never a diagnostic logging context. */
    public static function error(string $msg = '', int $httpStatus = 400, mixed $data = null, array $headers = []): self
    {
        $payload = ['status' => false, 'msg' => $msg];
        if ($data !== null) $payload['data'] = $data;
        return self::json($payload, $httpStatus, $headers);
    }

    /** Unwinds handlers and middleware; App emits the response and runs finally. */
    public function throwSend(): never
    {
        throw new ResponseReady($this);
    }

    /** Output only; normally called by App, does not terminate execution. */
    public function emit(): void
    {
        if (headers_sent()) {
            throw new \RuntimeException('Cannot send JSON response after headers were sent');
        }
        header_remove('Location');
        header_remove('Content-Length');
        foreach ($this->headers as $name => $value) header($name . ': ' . $value, true);
        // Location can implicitly select 302; the explicit response status wins.
        http_response_code($this->httpStatus);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') echo $this->body;
    }
}
