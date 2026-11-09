<?php

namespace SiteApi\Root\Clients\Email;

use PHPMailer\PHPMailer\PHPMailer;
use SiteApi\Root\Messenger\{ClientContract, Message, Recipient, SendResult, Channel, MessagePresentation, DeliveryException, DeliveryErrorReporter, LogDeliveryErrorReporter};

class Client implements ClientContract
{
    /**
     * Создаёт SMTP-клиент с адресом отправителя, учётными данными и TLS/SSL.
     *
     * Сеть вызывается только при send(); при пустом username SMTPAuth выключен.
     * @param string $encryption Только tls или ssl; незашифрованный режим не поддерживается.
     * @throws \InvalidArgumentException Некорректная конфигурация SMTP.
     * @throws \LogicException Не установлен PHPMailer.
     */
    public function __construct(
        private readonly string $host,
        private readonly string $from,
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly int $port = 587,
        private readonly string $encryption = 'tls',
        private readonly string $fromName = '',
        private readonly DeliveryErrorReporter $reporter = new LogDeliveryErrorReporter(),
    ) {
        if (trim($host) === '' || preg_match('/[\r\n]/', $host . $fromName)
            || !filter_var($from, FILTER_VALIDATE_EMAIL) || $port < 1 || $port > 65535
            || !in_array($encryption, ['tls', 'ssl'], true)) {
            throw new \InvalidArgumentException('Invalid SMTP configuration');
        }
        if (!class_exists(PHPMailer::class)) throw new \LogicException('Install Composer dependencies for email');
    }

    /**
     * Возвращает канал EMAIL.
     */
    public function getChannel(): Channel { return Channel::EMAIL; }

    protected function createMailer(): PHPMailer { return new PHPMailer(true); }

    /**
     * Отправляет одно HTML-письмо с текстовой альтернативой, ссылками и вложениями.
     *
     * Текст экранируется; таймаут SMTP 30 секунд, автоматических повторов нет.
     * Операционный отказ отражается в SendResult; reporter не должен выбрасывать исключения.
     * @throws \InvalidArgumentException Чужой канал или недоступное вложение до отправки.
     */
    public function send(Message $message, Recipient $recipient): SendResult
    {
        if ($recipient->channel !== $this->getChannel()) throw new \InvalidArgumentException('Wrong recipient channel');
        foreach ($message->attachments as $file) $file->validate();
        $result = new SendResult($this->getChannel(), $message->reference);
        try {
            $mail = $this->createMailer();
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            $mail->SMTPSecure = $this->encryption;
            $mail->SMTPAuth = $this->username !== '';
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            $mail->Timeout = 30;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($this->from, $this->fromName);
            $mail->addAddress($recipient->address);
            $mail->Subject = trim($message->subject) !== '' ? $message->subject : 'Сообщение';
            $html = '<!doctype html><html lang="ru"><meta charset="utf-8"><body><p>' . nl2br(MessagePresentation::escape($message->text)) . '</p>';
            $plain = $message->text;
            foreach ($message->actions as $link) {
                $html .= '<p><a href="' . MessagePresentation::escape($link->url) . '">' . MessagePresentation::escape($link->label) . '</a></p>';
                $plain .= "\n\n" . $link->label . ': ' . $link->url;
            }
            // PHPMailer requires a non-empty body, including for attachment-only messages.
            if (trim($plain) === '') {
                $plain = MessagePresentation::heading($message);
                $html = '<!doctype html><html lang="ru"><meta charset="utf-8"><body><p>' . MessagePresentation::escape($plain) . '</p>';
            }
            $mail->isHTML(true);
            $mail->Body = $html . '</body></html>';
            $mail->AltBody = $plain;
            foreach ($message->attachments as $file) $mail->addAttachment($file->path, $file->name, 'base64', $file->mimeType);
            if (!$mail->send()) throw new DeliveryException('provider_rejected');
            $result->accepted('email', $mail->getLastMessageID());
        } catch (\Throwable $error) {
            $reason = $error instanceof DeliveryException ? $error->reason : 'delivery_failed';
            $result->failed('email', $reason);
            $this->reporter->report($result, 'email', $reason, $error);
        }
        return $result;
    }
}
