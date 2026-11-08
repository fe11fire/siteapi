<?php
/** Offline email adapter test with a PHPMailer double, not an SMTP integration test. */
namespace PHPMailer\PHPMailer {
    #[\AllowDynamicProperties]
    class PHPMailer {
        public static ?self $last = null;
        public static bool $fail = false;
        public array $attachments = [];
        public function __construct(bool $exceptions) { self::$last = $this; }
        public function isSMTP(): void {}
        public function isHTML(bool $enabled): void {}
        public function setFrom(string $address, string $name): void { $this->from = $address; }
        public function addAddress(string $address): void { $this->to = $address; }
        public function addAttachment(...$args): void { $this->attachments[] = $args; }
        public function send(): bool { if (self::$fail) throw new \RuntimeException('sensitive provider details'); return true; }
        public function getLastMessageID(): string { return '<test@example.com>'; }
    }
}
namespace {
    spl_autoload_register(function (string $class): void {
        if (str_starts_with($class, 'SiteApi\\')) {
            $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
            if (is_file($file)) require $file;
        }
    });
    function check(bool $value): void { if (!$value) throw new \RuntimeException('Email check failed'); }
    $client = new \SiteApi\Root\Clients\Email\Client('smtp.example.com', 'sender@example.com');
    $sender = new \SiteApi\Root\Messenger\Sender($client);
    $recipient = new \SiteApi\Root\Messenger\Recipient('email', 'recipient@example.com');
    $message = new \SiteApi\Root\Messenger\Message(subject: 'Order', text: '<script>private</script>',
        actions: [new \SiteApi\Root\Messenger\Link('A & B', 'https://example.com/?a=1&b=2')]);
    $result = $sender->send($message, $recipient);
    $mail = \PHPMailer\PHPMailer\PHPMailer::$last;
    check($result->getStatus() === 'success');
    check($mail->Subject === 'Order' && $mail->to === 'recipient@example.com');
    check(str_contains($mail->Body, '&lt;script&gt;') && !str_contains($mail->Body, '<script>'));
    check(str_contains($mail->AltBody, 'A & B: https://example.com/'));
    check($mail->SMTPSecure === 'tls' && $mail->SMTPAuth === false);
    $path = tempnam(sys_get_temp_dir(), 'siteapi-email-');
    try {
        file_put_contents($path, 'document');
        $sender->send(new \SiteApi\Root\Messenger\Message(attachments: [new \SiteApi\Root\Messenger\Attachment($path)]), $recipient);
        $mail = \PHPMailer\PHPMailer\PHPMailer::$last;
        check($mail->AltBody === 'Вложения' && count($mail->attachments) === 1);
    } finally { unlink($path); }
    \PHPMailer\PHPMailer\PHPMailer::$fail = true;
    check($sender->send($message, $recipient)->getStatus() === 'failed');
    echo "Email adapter offline checks passed\n";
}
