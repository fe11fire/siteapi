<h1 align="center">SiteApi</h1>

**SiteApi** - framework для реализации web-приложения через одну точку входа.

Разработан в результате анализа принципов работы других framework (в первую очередь [Laravel](https://laravel.com/)), а также с целью получения собственного простого инструмента для быстрой реализации сайтов и web-приложений.

## Оглавление
- [Оглавление](#оглавление)
- [Установка](#установка)
- [Настройка](#настройка)
- [Основной функционал](#основной-функционал)
  - [Маршрутизация](#маршрутизация)
  - [Middlewares](#middlewares)
  - [Построение html-файлов](#построение-html-файлов)
  - [Логирование](#логирование)
  - [Кэширование](#кэширование)
  - [Работа с CURL](#работа-с-curl)
  - [Отправка сообщений (Telegram)](#отправка-сообщений-telegram)
  - [Работа с СУБД](#работа-с-субд)
  - [Режимы разработки](#режимы-разработки)

## Установка
<br>Предварительно необходимо настроить Composer, добавив в списки репозиториев файла `composer.json` ссылку на проект:
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/fe11fire/siteapi.git"
        }
    ],
}
```
Далее в командной строке выполнить команду:
```
composer require fellfire/siteapi:dev-main
```
На данном этапе разработки версионность проекта не используется.

## Настройка

<br>Необходимо перенаправить все запросы web-сервера в точку входа `index.php`.

Пример файла `.htaccess` для `Apache`:
```
RewriteEngine on
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php
```

Пример файла `index.php`:
```php
use SiteApi\Root\Http\Router;
use SiteApi\Root\Http\Templates;
use SiteApi\Root\Http\ErrorHandler;
use SiteApi\Root\Settings\Settings;

require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');

$bufferLevel = ob_get_level();
ob_start();

try {
    Settings::init();
    Router::init($_SERVER['REQUEST_URI']);

    if (Router::route(Router::get_Route())) {
        return;
    }

    // false означает только отсутствие маршрута.
    http_response_code(404);
    if (Router::is_Ajax()) {
        echo Templates::pageAjax();
        return;
    }
    if (Router::route('404')) {
        return;
    }
    echo Templates::page404();
} catch (\Throwable $error) {
    // Удаляем частичный ответ и вложенные буферы страницы.
    while (ob_get_level() > $bufferLevel) {
        ob_end_clean();
    }
    // Ответ об ошибке также отправляется через finally.
    ob_start();
    ErrorHandler::handle($error);
    return;
} finally {
    // finally выполняется и при раннем return.
    // Исходные буферы, открытые до точки входа, не трогаем.
    while (ob_get_level() > $bufferLevel) {
        ob_end_flush();
    }
}
```

Settings загружает только файлы с расширением `.json` из каталога настроек.
Каждый файл должен содержать JSON-объект верхнего уровня (в том числе допустим `{}`).
Ошибки чтения, синтаксиса и структуры прерывают инициализацию исключением с именем файла,
без включения его содержимого. Настройки публикуются после проверки всех файлов,
поэтому частично загруженная конфигурация не используется.
Проверка: `php tests/settings.php`.

## Основной функционал
### Маршрутизация
<br>Добавить каталог `routes` в корне web-приложения.

Список маршрутов берется из **всех** файлов каталога:
```
📦 routes
 ┣ 📜 admin.php
 ┣ ...
 ┣ 📜 pay.php
 ┣ ...
 ┗ 📜 web.php
```

Оформление маршрута на примере файла `web.php`:
```php
return [
    [
        'name' => '',
        'path' => 'pages/main/',
        'url' => 'index.php',
    ],
    [
        'name' => 'about',
        'path' => 'pages/about/',
        'url' => 'index.php',
    ],
]
```
### Middlewares

Middleware маршрута должен существовать и реализовывать `SiteApi\Root\Http\Contracts\Middleware`.
При неверной конфигурации роутер выбрасывает исключение и не выполняет страницу.

### Обработка ошибок

Внешний `catch (\Throwable $error)` в точке входа передаёт ошибки в `ErrorHandler`.
Обработчик пишет подробности с уровнем Error через `LogHelper::error()` в `errors/`
(по умолчанию `siteapilogs/<год>/errors/<месяц-день>.log`). При отказе логгера используется PHP `error_log()`.
Пользователь получает нейтральную страницу и код обращения, совпадающий с `error_id` в журнале.
Для пути `ajax` / `ajax/...` возвращается JSON. Заголовок `X-Requested-With` не учитывается.
Ответ имеет статус 500; отсутствие маршрута обрабатывается отдельно как 404.
Обработчик не отправляет уведомления администратору.

В примере точки входа вывод накапливается в буфере. При ошибке частичный ответ
и вложенные буферы удаляются до вызова `ErrorHandler`. Блок `finally` отправляет
готовый ответ даже при раннем `return`, сохраняя буферы, открытые до точки входа.
Код страниц не должен принудительно отправлять или закрывать буфер точки входа.
`exit` / `die` не выполняют `finally`; потоковые ответы и скачивания требуют отдельного сценария.
Уже отправленные данные и заголовки буфер не восстанавливает.
В production отключите `display_errors` в настройках PHP, чтобы встроенные сообщения PHP
не раскрывали подробности.

### Построение html-файлов
<br>

### Логирование
<br>

### Кэширование

Доступны `CacheEnum::REDIS` (Predis) и `CacheEnum::MEMCACHED` (PHP-расширение `memcached`). Без явного выбора используется Memcached.

```php
use SiteApi\Root\Providers\Cache\CacheEnum;
use SiteApi\Root\Providers\Cache\CacheProvider;

CacheProvider::init(CacheEnum::REDIS, 'my-app'); // второй аргумент — пространство ключей
CacheProvider::set('user:42', ['name' => 'Иван'], 300);
$user = CacheProvider::get('user:42');
$value = CacheProvider::get('missing', 'default');

$result = CacheProvider::read('user:42');
if ($result['hit']) {
    $user = $result['value'];
}

CacheProvider::has('user:42');
CacheProvider::delete('user:42');
$value = CacheProvider::remember('calculated', fn() => calculateValue(), 300);
```

`get()` возвращает `false` при отсутствии ключа, если не указан другой default. Сохранённые `false` и `null` возвращаются без замены на default; `read()` возвращает массив `array{hit: bool, value: mixed}` с независимыми полями `hit` и `value`. Между отдельными вызовами `has()` и `get()` запись может истечь, поэтому для точного чтения используйте `read()`.

Период задаётся в секундах: по умолчанию 299, `0` — без истечения, отрицательные значения запрещены. Сроки свыше 30 дней автоматически преобразуются для Memcached. Кэш всё равно может удалить запись при нехватке памяти. `remember()` не блокирует параллельное вычисление одного ключа.

Обе реализации сохраняют PHP-типы через общий формат сериализации: строки (включая бинарные), числа, массивы, bool, null и сериализуемые объекты. Классы объектов должны быть доступны при чтении. Ресурсы и замыкания не поддерживаются; чрезмерно глубокие структуры и рекурсивные массивы отклоняются. Объекты с собственными методами сериализации отвечают за сериализуемость своего представления. Восстановление объектов вызывает PHP-методы десериализации: используйте только доверенный сервер кэша.

Адрес и порт читаются из `redis.host` / `redis.port` либо `memcached.host` / `memcached.port` в настройках Settings. Также поддерживаются переменные окружения `REDIS_HOST`, `REDIS_PORT`, `MEMCACHED_HOST`, `MEMCACHED_PORT`. Значения по умолчанию: `redis:6379` и `memcached:11211`.

`CacheProvider::status()` проверяет соединение и возвращает bool. Ошибки операций чтения, записи и удаления выбрасывают исключения; повреждённый формат вызывает `UnexpectedValueException`. Удаление отсутствующего ключа допустимо.

Совместимость: `CacheProvider::init('Redis')` и `init('Memcached')` продолжают работать. Старые записи изолированы новым версионным пространством ключей и не читаются; после обновления кэш наполняется заново. Прямые статические вызовы методов Redis/Memcached и собственные реализации старого CacheContract требуют обновления: адаптеры теперь являются объектами и работают только с закодированными строками, а общий API значений находится в CacheProvider. Поддержка Memcache удалена.

Интеграционная проверка: запустите отдельные тестовые Redis и Memcached, укажите их адреса переменными окружения и выполните `php tests/cache.php`. Проверка создаёт уникальное пространство ключей и удаляет свои записи, не очищая сервер целиком.
### Работа с CURL
<br>

### Отправка сообщений (Telegram)
<br>

### Работа с СУБД

`DBHelper::selectExactlyOne()` заменяет прежний `selectSingle()` (внешние вызовы нужно переименовать).
Аргументы сохранены. Метод записывает единственную найденную строку в выходной параметр
и возвращает `true`. Если строк нет — возвращает `false`, выходной параметр равен `[]`.
Если строк больше одной — выбрасывает `UnexpectedValueException`, выходной параметр
остаётся `[]`. Ошибки выполнения SQL не превращаются в отсутствие строки и передаются выше.
При поиске по уникальному признаку закрепляйте уникальность также ограничением в БД.
Не добавляйте `LIMIT 1`, если требуется обнаруживать несколько совпадений.

Условия с `null` означают SQL `IS NULL`, без добавления значения в параметры PDO:

```php
$where = ['user_id' => 42, 'deleted_at' => null];
// WHERE (user_id = ?) AND (deleted_at IS NULL)

$where = ['verified_at' => [null, 'IS NOT NULL']];
// WHERE (verified_at IS NOT NULL)

$where = [
    'status' => 'active',
    new \SiteApi\Root\Helpers\RawCondition('updated_at > created_at AND age >= ?', [18]),
];
```

RawCondition принимает доверенное SQL-выражение и список позиционных значений для PDO.
Не вставляйте пользовательские данные в его SQL-строку. Условия объединяются через AND,
выражение RawCondition заключается в скобки. Прежнюю запись `['deleted_at IS NULL' => null]`
нужно заменить на `['deleted_at' => null]` или явный RawCondition.
Для null допустимы только `IS NULL` и `IS NOT NULL`; сравнение `[null, '=']` отклоняется.
Обычные сравнения вида `['age' => [18, '>=']]` сохранены.

`DBHelper::selectCount()` считает строки результата на стороне БД через
`SELECT COUNT(*) FROM (исходный SELECT) AS counted_rows`. PHP получает одну строку с числом,
а не всю выборку. Смысл GROUP BY, HAVING, DISTINCT, LIMIT и OFFSET сохраняется:
это число строк результата, а не обязательно всех записей таблицы для пагинации.
SQL для select() и selectCount() собирается общим методом.

Для MySQL колонки внутреннего SELECT должны иметь уникальные имена: при JOIN вместо
`SELECT *` перечислите нужные поля с разными алиасами, например
`orders.id AS order_id, users.id AS user_id`. Это ограничение обёртки подзапросом;
существующие вызовы с одинаковыми именами колонок потребуется обновить.
Части SQL должны быть допустимы внутри подзапроса; ошибки БД передаются вызывающему коду.

Соединение PDO создаётся при первом SQL-запросе или `beginTransaction()` и повторно
используется до завершения обычного PHP-запроса (PHP-FPM/Apache). Результаты SELECT
считываются в массивы и не меняются при следующих запросах. Изменение Settings после
подключения не переключает существующее соединение. Постоянные подключения не включены.

```php
use SiteApi\Root\Helpers\DBHelper;

DBHelper::beginTransaction();
try {
    DBHelper::universal('UPDATE accounts SET balance = balance - ? WHERE id = ?', [100, 1]);
    DBHelper::universal('UPDATE accounts SET balance = balance + ? WHERE id = ?', [100, 2]);
    DBHelper::commit();
} catch (\Throwable $error) {
    if (DBHelper::inTransaction()) {
        DBHelper::rollBack();
    }
    throw $error;
}
```

`inTransaction()` проверяет состояние без создания соединения. Вложенные транзакции
не поддерживаются; повторный begin и commit/rollback без активной транзакции обрабатываются
PDO как ошибки. Используйте транзакционные таблицы (например, MySQL InnoDB).
Некоторые SQL-команды, включая DDL в MySQL, могут автоматически завершать транзакцию.
Автоматических переподключений и повторов SQL нет: они могли бы нарушить транзакцию
или повторно применить изменения. Для RoadRunner/Swoole и параллельных задач внутри
одного процесса требуется отдельное управление жизненным циклом соединений;
статический DBHelper для этого режима не предназначен.

Проверка повторного использования PDO и транзакций на SQLite: `php tests/database.php`.
Она не заменяет интеграционную проверку с MySQL.

### Режимы разработки
<br>


## Отправка сообщений: Telegram и email

Новый API заменяет прежний `Sender::send(...)`, массив адресов Recipient и обязательные
классы шаблонов Message. Поддерживаются только исходящие сообщения через `telegram` и `email`.
Приём команд и событий, webhook и сценарии диалогов не реализованы.

```php
use SiteApi\Root\Messenger\{Sender, Message, Recipient, Attachment, Link};
use SiteApi\Root\Clients\Telegram\Client as TelegramClient;
use SiteApi\Root\Clients\Email\Client as EmailClient;

$sender = new Sender(
    new TelegramClient(token: Settings::get('telegram', 'token')),
    new EmailClient(
        host: Settings::get('smtp', 'host'),
        from: Settings::get('smtp', 'from'),
        username: Settings::get('smtp', 'username'),
        password: Settings::get('smtp', 'password'),
        port: 587,
        encryption: 'tls', // tls (STARTTLS) или ssl (implicit TLS)
    ),
);

$message = new Message(
    subject: 'Заказ №125 готов',
    text: "Ваш заказ готов к выдаче.\nЗабрать его можно до 20:00.",
    attachments: [new Attachment('/absolute/path/receipt.pdf', 'Чек.pdf', 'application/pdf')],
    actions: [new Link('Посмотреть заказ', 'https://example.com/orders/125')],
    reference: 'order-125', // внутреннее поле, получателю не показывается
);

$result = $sender->send($message, new Recipient('telegram', '-123456789'));
// Для email тот же Message:
$result = $sender->send($message, new Recipient('email', 'user@example.com'));

$result->getStatus(); // success, partial, failed
$result->operationId; // код для поиска ошибки в журнале
$result->getParts();  // part, success, provider_id для каждой выполненной попытки
```

Можно настроить только один клиент. Настройки читаются приложением и передаются в конструктор;
пароли и токены не записываются в журнал модулем. Telegram использует HTTPS через PHP cURL,
email — PHPMailer через SMTP с проверкой TLS. Нужны Composer-зависимости и PHP ext-curl.
Текст — UTF-8, без пользовательской HTML-разметки; специальные символы экранируются.
Email содержит HTML и текстовую альтернативу, тема без subject — «Сообщение».
Telegram оформляет subject жирным, actions — отдельными рядами inline-кнопок.
Если темы и текста нет, Telegram отправляет «Вложения» перед файлами или «Полезные ссылки»
перед кнопками. В email для пустого тела также используется короткая подпись.

Вложения — только читаемые локальные файлы, ссылки-действия — только HTTP(S).
Telegram проверяет длину текста (до 4096 UTF-16 единиц, консервативно), до 100 кнопок,
подпись кнопки до 256 байт и файл до 50 MB перед первой отправкой. Авторазбиения нет.
SMTP-ограничения размера задаются почтовым сервером; отказ вернётся как failed.
В Telegram сначала отправляется текст, затем документы по порядку. Если текст не отправлен,
документы не отправляются; ошибка отдельного документа не отменяет остальные попытки.
Части, до которых выполнение не дошло, отсутствуют в getParts().
Автоматических повторов нет. После сетевого таймаута результат доставки может быть неизвестен:
повторная отправка способна создать дубликат.

Ошибки данных/конфигурации выбрасывают исключения до отправки. Ошибки доставки возвращаются
в SendResult и логируются через LogHelper с operation_id, каналом и частью сообщения.
Тела сообщений, адреса, reference и сырые сообщения исключений провайдера не логируются:
они могут содержать секреты. При отказе LogHelper используется error_log().
Success означает принятие сервисом, а не прочтение или гарантированную доставку во входящие.

Офлайн-проверки без реальной отправки: `php tests/messenger.php` и `php tests/email.php`.
Email-проверка использует двойник PHPMailer; реальную SMTP-доставку она не подтверждает.


### Структура модуля отправки

`Channel` задаёт два разрешённых канала. `Recipient` принимает enum или строку,
но хранит `Channel`. `ClientContract` содержит только `getChannel(): Channel`
и `send(Message, Recipient): SendResult`. `Sender` получает клиентов через конструктор.

`Message` хранит содержимое и проверяет его структуру; оформление вынесено в
`MessagePresentation`. `SendResult` только хранит исходы попыток и вычисляет статус.
Ошибочная часть дополнительно содержит безопасную категорию `reason`:
`timeout`, `transport_failed`, `provider_rejected` или общую `delivery_failed`.
Для исключений PHPMailer пока используется общая категория; классификация
аутентификации по тексту исключения не выполняется.

Оба клиента принимают необязательный `DeliveryErrorReporter`. По умолчанию это
`LogDeliveryErrorReporter`, использующий LogHelper и резервный error_log().
Собственная реализация должна обеспечивать отказоустойчивое логирование и не выбрасывать
исключения из report(). В результат не попадают исходные исключения и ответы сервисов.
Добавление третьего канала потребует расширения Channel и проверки адреса Recipient —
это осознанное ограничение текущей версии, а не автоматически подключаемая система плагинов.
