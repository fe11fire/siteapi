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
<br>

### Режимы разработки
<br>
