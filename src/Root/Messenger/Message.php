<?php

namespace SiteApi\Root\Messenger;

final class Message
{
    /**
     * Создаёт сообщение с темой, обычным текстом, вложениями и ссылками.
     *
     * Тема/текст должны быть UTF-8; HTML трактуется как текст и экранируется клиентами.
     * Ограничения конкретного канала проверяются при отправке.
     * @param Attachment[] $attachments Локальные вложения.
     * @param Link[] $actions Ссылки-действия.
     * @param string|null $reference Прикладной идентификатор для сопоставления результата.
     * @throws \InvalidArgumentException Пустое сообщение, неверная кодировка или тип элемента.
     */
    public function __construct(
        public readonly string $subject = '',
        public readonly string $text = '',
        public readonly array $attachments = [],
        public readonly array $actions = [],
        public readonly ?string $reference = null,
    ) {
        foreach ([$subject, $text] as $value) {
            if (!preg_match('//u', $value)) throw new \InvalidArgumentException('Message must be UTF-8');
        }
        foreach ($attachments as $item) {
            if (!$item instanceof Attachment) throw new \InvalidArgumentException('Expected Attachment');
        }
        foreach ($actions as $item) {
            if (!$item instanceof Link) throw new \InvalidArgumentException('Expected Link');
        }
        if (trim($subject . $text) === '' && !$attachments && !$actions) {
            throw new \InvalidArgumentException('Message is empty');
        }
    }

}
