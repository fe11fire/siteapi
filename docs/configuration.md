# Настройки

[Обзор проекта](../readme.md)

## Источники

Settings читает JSON-файлы из `settings/main`. Имя файла образует секцию: `db.json` → `db`. Каждый файл должен содержать объект, включая допустимый пустой `{}`. Файлы других расширений пропускаются.

При запросе значения сначала проверяется окружение. `Settings::getString('db', 'password')` ищет `DB_PASSWORD`, затем `password` в db.json. `.env` сам не загружается.

JSON проверяется с исключениями: ошибки чтения, синтаксиса и верхнего уровня сообщают имя файла, но не его содержимое. Новая конфигурация публикуется после проверки всех файлов. Инициализация предназначена для начала запроса, а не для горячей перезагрузки работающего приложения.

`Settings::init()` требует каталог. `App::run()` вызывает `init(allowMissing: true)` и допускает его отсутствие.

## Типизированные значения

```php
<?php
use SiteApi\Root\Settings\Settings;

$enabled = Settings::getBool('app', 'enabled', default: false);
$port = Settings::getInt('redis', 'port', default: 6379);
$password = Settings::getString('db', 'password');
$hosts = Settings::getArray('app', 'allowed_hosts', default: []);
```

| Метод | Принимаемые значения |
|---|---|
| getString | Только строка, без преобразования чисел |
| getBool | true/false, 1/0, строки `true`/`false`, `1`/`0` |
| getInt | int или десятичная строка в диапазоне PHP int, без пробелов, дробей и ведущих нулей |
| getArray | PHP-массив либо строка с JSON-массивом; JSON-объекты остаются stdClass |

Default применяется только при отсутствии ключа. Неверный тип и явный null вызывают исключение; default тоже проходит проверку. Числовые идентификаторы, ожидаемые строками, записывайте в JSON в кавычках.

`get()` и `get_Default()` возвращают исходное mixed-значение. Они различают отсутствующее свойство и null. Переменные окружения в этих методах остаются строками.

## Стандартные пути

| Ключ | Значение |
|---|---|
| routes | routes |
| middlewares | scripts/middlewares |
| headers | common/headers |
| logs | siteapilogs |

Переопределения задаются в `paths.json`:

```json
{
    "routes": "app/routes",
    "middlewares": "app/middlewares",
    "headers": "app/headers",
    "logs": "storage/logs"
}
```

Доступ: `get_Directory('routes')`, `get_Directory_Default('custom', 'fallback')`.
Существующая реализация проверяет окружение для пути только после обнаружения соответствующего свойства в секции paths. Поэтому не рассчитывайте на PATHS_LOGS как единственное объявление пути: сначала задайте ключ в paths.json.

## Настройки сервисов

| Модуль | Поля |
|---|---|
| DBHelper | db.server, db.name, db.user, db.password |
| Redis | redis.host, redis.port; по умолчанию redis:6379 |
| Memcached | memcached.host, memcached.port; по умолчанию memcached:11211 |
| Redirect | env.domain, env.host, env.folder |
| Mode | env.mode |

Telegram и email получают настройки в конструкторе клиента: автоматически из Settings они не создаются.

Для приложения в корне домена:

```json
{
    "domain": "example.com",
    "host": "",
    "folder": "",
    "mode": "prod"
}
```

Это пример env.json. Redirect склеивает domain и host без разделителя и всегда использует HTTPS. Folder участвует в сопоставлении маршрутов; развёртывание в подкаталоге требует отдельно проверить пути, AJAX и ссылку на главную встроенных страниц.

## Режим приложения

`Mode::printIf($text, Mode::CONDITION_DEV)` возвращает текст только при совпадении с env.mode; также есть CONDITION_PROD и CONDITION_ANY. Это условный вывод, а не переключатель всех настроек безопасности и отладки.
