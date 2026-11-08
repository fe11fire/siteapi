<?php
/** Offline contract checks. No messages are sent. */
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'SiteApi\\')) {
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
        if (is_file($file)) require $file;
    }
});

use SiteApi\Root\Messenger\{Message, Attachment, Link, Recipient, Sender};
use SiteApi\Root\Clients\Telegram\Client;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function invalid(callable $call): void {
    try { $call(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Expected validation error');
}
final class FakeTelegram extends Client {
    public array $calls = [];
    public ?int $failAt = null;
    protected function request(string $method, array $payload): array {
        $this->calls[] = [$method, $payload];
        if ($this->failAt === count($this->calls)) throw new RuntimeException('secret provider details');
        return ['message_id' => count($this->calls)];
    }
}
$path = tempnam(sys_get_temp_dir(), 'siteapi-message-');
file_put_contents($path, 'test document');
try {
    $file = new Attachment($path, 'test.txt', 'text/plain');
    $recipient = new Recipient('telegram', '-12345');
    $reporter = new class implements \SiteApi\Root\Messenger\DeliveryErrorReporter {
        public array $entries = [];
        public function report(\SiteApi\Root\Messenger\SendResult $result, string $part, string $reason, Throwable $error): void {
            $this->entries[] = [$result->operationId, $part, $reason];
        }
    };
    $client = new FakeTelegram('12345:test_token', $reporter);
    $sender = new Sender($client);
    $message = new Message(subject: 'A < B', text: 'Text & more', attachments: [$file],
        actions: [new Link('Open', 'https://example.com/?a=1&b=2')], reference: 'order-1');
    $result = $sender->send($message, $recipient);
    check($result->getStatus() === 'success' && count($result->getParts()) === 2, 'Success result');
    check($result->reference === 'order-1', 'Reference');
    check($client->calls[0][0] === 'sendMessage' && $client->calls[1][0] === 'sendDocument', 'Order');
    check(str_contains($client->calls[0][1]['text'], '<b>A &lt; B</b>'), 'Escaped subject');
    check(str_contains($client->calls[0][1]['text'], 'Text &amp; more'), 'Escaped text');
    check(json_decode($client->calls[0][1]['reply_markup'], true)['inline_keyboard'][0][0]['text'] === 'Open', 'Buttons');
    $client->calls = [];
    $sender->send(new Message(attachments: [$file]), $recipient);
    check(str_contains($client->calls[0][1]['text'], 'Вложения'), 'Default attachment heading');
    $client->calls = [];
    $sender->send(new Message(actions: [new Link('Open', 'https://example.com')]), $recipient);
    check(str_contains($client->calls[0][1]['text'], 'Полезные ссылки'), 'Default links heading');
    $client->calls = [];
    $client->failAt = 2;
    check($sender->send($message, $recipient)->getStatus() === 'partial', 'Partial delivery');
    check($reporter->entries[0][1] === 'attachment:0', 'Injected reporter receives failed part');
    $client->calls = [];
    $client->failAt = 1;
    check($sender->send($message, $recipient)->getStatus() === 'failed' && count($client->calls) === 1, 'Stop after text failure');
    $client->calls = [];
    invalid(fn() => $sender->send(new Message(text: str_repeat('a', 4097)), $recipient));
    check(!$client->calls, 'Validate before network');
    invalid(fn() => new Message());
    invalid(fn() => new Link('Bad', 'javascript:alert(1)'));
    invalid(fn() => new Recipient('sms', '123'));
    invalid(fn() => new Recipient('email', 'not-an-email'));
    invalid(fn() => new Attachment('https://example.com/file'));
    invalid(fn() => new Sender($client, $client));
    try {
        $sender->send(new Message(text: 'Hello'), new Recipient('email', 'test@example.com'));
        throw new RuntimeException('Expected unconfigured channel');
    } catch (LogicException) {}
    echo "Messenger offline checks passed\n";
} finally {
    unlink($path);
}
