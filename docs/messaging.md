# Отправка сообщений

[Обзор проекта](../readme.md)

Модуль синхронно отправляет сообщения через Telegram и email. Приём событий, webhook и диалоги бота не реализованы. Telegram Bot API используется только для исходящей доставки; токен бота всё равно необходим.

## Общая модель

| Объект | Данные |
|---|---|
| Message | subject, text, attachments, actions, reference |
| Recipient | Channel или строка канала и адрес |
| Attachment | Читаемый локальный файл, отображаемое имя и MIME-тип |
| Link | Подпись и HTTP(S)-URL |
| SendResult | operationId, channel, reference, результаты попыток |

Message принимает обычный UTF-8 текст без пользовательской разметки. Хотя бы одна содержательная часть обязательна. Reference — внутренний идентификатор события, он не показывается получателю.

## Настройка клиентов

```php
<?php
use SiteApi\Root\Messenger\{Sender, Message, Recipient, Attachment, Link};
use SiteApi\Root\Clients\Telegram\Client as TelegramClient;
use SiteApi\Root\Clients\Email\Client as EmailClient;
use SiteApi\Root\Settings\Settings;

$sender = new Sender(
    new TelegramClient(token: Settings::getString('telegram', 'token')),
    new EmailClient(
        host: Settings::getString('smtp', 'host'),
        from: Settings::getString('smtp', 'from'),
        username: Settings::getString('smtp', 'username'),
        password: Settings::getString('smtp', 'password'),
        port: Settings::getInt('smtp', 'port', default: 587),
        encryption: 'tls',
    ),
);

$message = new Message(
    subject: 'Заказ №125 готов',
    text: "Ваш заказ готов к выдаче.\nЗабрать его можно до 20:00.",
    attachments: [new Attachment('/absolute/path/receipt.pdf', 'Чек.pdf', 'application/pdf')],
    actions: [new Link('Посмотреть заказ', 'https://example.com/orders/125')],
    reference: 'order-125',
);

$result = $sender->send($message, new Recipient('telegram', '-123456789'));
// Для письма: new Recipient('email', 'user@example.com').
```

Можно настроить только один клиент. Дублирующийся канал и ненастроенный канал получателя — ошибки конфигурации. Настройки подключения передаются явно; SMTP использует TLS (`tls` для STARTTLS, `ssl` для implicit TLS), аутентификация включается при непустом username.

## Представление по каналам

| Содержимое | Email | Telegram |
|---|---|---|
| subject | Тема письма | Жирный заголовок |
| text | HTML с экранированием + текстовая альтернатива | Экранированный текст |
| actions | Подписанные ссылки | Inline-кнопки отдельными рядами |
| attachments | Вложения одного письма | Документы отдельными сообщениями |

Если темы и текста нет, Telegram отправляет «Вложения» перед файлами либо «Полезные ссылки» перед кнопками. Для пустого тела email также есть подпись; без subject тема письма — «Сообщение».

URL в обычном тексте не превращаются автоматически в actions. Callback-кнопок нет. Удалённые URL вложений не загружаются.

## Порядок и результат

Telegram сначала отправляет текст с кнопками, затем файлы по порядку. При отказе текста документы не отправляются. Ошибка отдельного файла не отменяет остальные попытки. Email — одна операция отправки.

```php
<?php
$status = $result->getStatus(); // success, partial, failed
$id = $result->operationId;
$parts = $result->getParts();   // part, success, provider_id; reason при ошибке
```

Success означает принятие сервисом, а не прочтение или гарантированную доставку во входящие. В список входят только выполненные попытки; skipped отдельно не представлен. SendResult изменяемый, его методы accepted/failed предназначены для клиента доставки.

Не выполняются автоматические повторы: после таймаута исход операции может быть неизвестен.

## Проверки и диагностика

До отправки Telegram проверяет текст до 4096 UTF-16 единиц (консервативно), до 100 кнопок, подпись до 256 байт и файл до 50 MB. Авторазбиения текста нет. SMTP-лимиты размера определяет сервер.

Ошибки данных и конфигурации вызывают исключения. Ошибки доставки дают результат с категорией timeout, transport_failed, provider_rejected или delivery_failed. Для PHPMailer пока используется общая категория.

DeliveryErrorReporter получает метаданные ошибки. Реализация по умолчанию использует LogHelper и резервный error_log. Тела, адреса, reference и сырые сообщения исключений не записываются. Собственный reporter должен сам обрабатывать отказы и не бросать исключения.

[Архитектура контрактов](architecture.md) · [Проверки](testing.md)
