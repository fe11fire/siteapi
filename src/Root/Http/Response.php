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

    /**
     * Создаёт JSON-ответ, не выводя данные и не завершая обработчик.
     *
     * Для передачи в App вызовите ->throwSend(). Кодирование выполняется сразу.
     * @param mixed $data JSON-совместимое значение.
     * @param int $httpStatus Статус 200–599, кроме 204, 205 и 304, не допускающих тело.
     * @param array<string, string> $headers Дополнительные заголовки без управляющих символов;
     *     Content-Type, Content-Length и Transfer-Encoding задавать нельзя.
     * @throws \InvalidArgumentException Недопустимый статус или заголовок.
     * @throws \JsonException Значение невозможно закодировать.
     */
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

    /**
     * Создаёт успешный ответ с полями status=true, code и data.
     *
     * Не отправляет ответ автоматически; далее вызовите ->throwSend().
     * @param int $code Прикладной код, не HTTP-статус; ID рекомендуется помещать в data.
     * @param mixed $data Публичные JSON-совместимые данные.
     * @throws \InvalidArgumentException Недопустимый статус или заголовок.
     * @throws \JsonException Ошибка кодирования данных.
     * @see self::json()
     */
    public static function ok(int $code = 0, mixed $data = '', int $httpStatus = 200, array $headers = []): self
    {
        return self::json(['status' => true, 'code' => $code, 'data' => $data], $httpStatus, $headers);
    }

    /**
     * Создаёт ответ с status=false и msg; data добавляется только при значении не null.
     *
     * Не отправляет ответ автоматически. В data передаются публичные данные,
     * а диагностический контекст следует писать в журнал, а не возвращать посетителю.
     * @param int $httpStatus HTTP-статус, по умолчанию 400.
     * @throws \InvalidArgumentException Недопустимый статус или заголовок.
     * @throws \JsonException Ошибка кодирования данных.
     * @see self::json()
     */
    public static function error(string $msg = '', int $httpStatus = 400, mixed $data = null, array $headers = []): self
    {
        $payload = ['status' => false, 'msg' => $msg];
        if ($data !== null) $payload['data'] = $data;
        return self::json($payload, $httpStatus, $headers);
    }

    /**
     * Прерывает обработчик, передавая этот ответ в App через ResponseReady.
     *
     * Сам ничего не выводит. App очистит буферы, вызовет emit() и выполнит finally.
     * Используется только внутри App::run(); промежуточный catch(Throwable) должен
     * пропускать ResponseReady дальше. Блоки finally обработчика также выполняются.
     * @throws ResponseReady Сигнал готового ответа, а не ошибка приложения.
     */
    public function throwSend(): never
    {
        throw new ResponseReady($this);
    }

    /**
     * Выводит HTTP-статус, заголовки и JSON; обычно вызывается только App.
     *
     * Не завершает выполнение и не очищает буферы. Удаляет прежние Location/Content-Length,
     * сохраняет прочие заголовки, включая cookie. Явный статус устанавливает после заголовков.
     * Для HEAD тело не выводится.
     * @throws \RuntimeException Заголовки уже отправлены.
     */
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
