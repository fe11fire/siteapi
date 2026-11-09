# HTTP, маршруты и шаблоны

[Обзор проекта](../readme.md)

## Маршруты

Файлы каталога routes должны возвращать массивы правил. Роутер включает все файлы каталога без фильтра расширения: храните там только конфигурацию маршрутов.

```php
<?php
return [
    ['name' => '', 'path' => 'pages/', 'url' => 'home.php'],
    ['name' => 'profile', 'path' => 'pages/', 'url' => 'profile.php'],
    ['name' => 'ajax/save', 'path' => 'actions/', 'url' => 'save.php'],
    ['name' => '404', 'path' => 'pages/', 'url' => 'not-found.php'],
];
```

Путь выделяется через parse_url(), крайние слеши убираются. `/profile`, `/profile/` и `/profile/?tab=info` выбирают один маршрут. Имена сравниваются строго: `01` и `1` различаются. Методы HTTP роутер автоматически не ограничивает: обработчик проверяет REQUEST_METHOD самостоятельно.

`route()` выполняет файл и возвращает true; при отсутствии правила — false. При ошибке выбрасывается исключение. `get_Path()` и `get_Address()` относятся к выбранному маршруту и не предназначены для чтения до его выполнения.

Wrapper задаётся полем `wrapper`. Он получает переменную `$filename` с путём страницы и должен сам подключить её. Роутер использует require_once для wrapper и страницы: повторное выполнение одного файла в рамках запроса не гарантируется.

## Middleware

```php
<?php
use SiteApi\Root\Http\Contracts\Middleware;

final class RequireLogin implements Middleware
{
    public static function apply()
    {
        if (empty($_SESSION['user_id'])) {
            throw new RuntimeException('Authentication is required');
        }
    }
}
```

Имя класса добавляется в `middlewares` правила. Класс должен быть загружен через Composer либо файл из каталога middleware. Роутер проверяет существование и интерфейс и вызывает `apply()` до страницы. Возвращаемое значение middleware не используется.

Пример выше демонстрирует контракт, а не готовую авторизацию: приложение должно открыть сессию и самостоятельно выбрать корректный ответ 401/403 или перенаправление. Обычное RuntimeException будет обработано как 500.

## Параметры

```php
<?php
use SiteApi\Root\Http\Router;

$params = Router::get_Params();
$filter = Router::get_Param_by_Name('filter');
Router::apply_Param_by_Name('filter', ['status' => 'active']);
```

Query string разбирается parse_str(): строки и вложенные массивы сохраняются. `filter[status]=active` даёт `['filter' => ['status' => 'active']]`. POST и JSON-тело автоматически не считываются.

get_Param_by_Name() возвращает mixed; отсутствие и явный null сейчас возвращают false из-за isset(). Полный массив get_Params() сохраняет null. Ожидаемую структуру проверяет обработчик.

apply_Param_by_Name() создаёт или заменяет значение без HTML и конкатенации.

```php
<?php
Router::route('profile');               // Сохранить параметры.
Router::route('profile', []);           // Очистить параметры.
Router::route('profile', ['id' => 42]);  // Заменить параметры.
```

init() вызывается один раз. Для независимых сценариев теста доступен reset(), который очищает правила и состояние, но не отменяет включение PHP-файлов.

## AJAX и ошибки

AJAX определяется только первым сегментом пути `ajax`, включая сам `/ajax`. Заголовок X-Requested-With не учитывается. Успешный ответ создаёт обработчик: автоматической сериализации в JSON нет.

| Ситуация | Ответ App |
|---|---|
| Обычный маршрут отсутствует | 404: свой маршрут 404 или встроенная HTML-страница |
| AJAX-маршрут отсутствует | 404: JSON из pageAjax() |
| Ошибка обычного запроса | 500: нейтральная HTML-страница с error_id |
| Ошибка AJAX | 500: JSON с success=false, message, error_id |

Формат AJAX-404 сохранён исторически как массив `[0, 1, "Ошибка при отправке данных"]`; он отличается от объекта ошибки 500.

ErrorHandler логирует ошибку, но не рассылает уведомления. Частичный вывод удаляется App перед ответом 500, если буферы допускают очистку. Подробности: [архитектура](architecture.md).

## Редиректы

```php
<?php
Router::redirectSafe('search?sort=date#results', ['q' => 'чай & кофе']);
```

Safe кодирует новые параметры по RFC 3986, учитывает query string и фрагмент. Null пропускается, bool кодируется как 1/0, массивы поддерживаются. Сам маршрут должен быть доверенным и уже корректно оформленным.

Старый redirect() не кодирует значения и сохранён для совместимости. Оба метода отправляют Location и вызывают exit, который не выполняет finally.

## PHP-модули и HTML

```php
<?php
use SiteApi\Root\Http\Includer;
use SiteApi\Root\Http\Models\Module;

Includer::include_Module(Module::make('components/card.php', ['title' => 'Профиль']));
```

В card.php доступна `$title`. Если рядом есть style_card.php, он подключается один раз для данного имени модуля. Стили и шаблон выполняются в общей области переменных. Не передавайте ключи module, vars, filename, key, key_value.

include_Headers() ищет css.php, js.php и views.php в каталогах выбранных headers.

HTML-помощники Includer экранируют атрибуты и содержимое option. Значения inline CSS и JavaScript должны быть доверенными; экранирование не делает произвольный код безопасным.

Vars хранит общие значения запроса через set()/get(). make_JS_var() принимает простое ASCII-имя без зарезервированных слов и экранирует JSON для script; console_log() выводит аналогичный безопасно закодированный аргумент. Ошибка JSON вызывает JsonException без частичного script.

## JSON-ответы Response

`Response` создаёт объект ответа; `throwSend()` завершает текущий обработчик через внутренний сигнал, который перехватывает `App::run()`. App удаляет частичный вывод, отправляет ответ и выполняет свой `finally`. `finally` обработчика тоже выполняется. Не выводите данные из блоков очистки: App удалит их вместе с частичным ответом.

```php
use SiteApi\Root\Http\Response;

Response::ok(data: ['id' => 15], httpStatus: 201)->throwSend();
// HTTP 201: {"status":true,"code":0,"data":{"id":15}}

Response::error('Нет доступа', httpStatus: 403)->throwSend();
// HTTP 403: {"status":false,"msg":"Нет доступа"}

Response::json(['items' => []], headers: ['Cache-Control' => 'no-store'])->throwSend();
```

`ok(int $code = 0, mixed $data = '', int $httpStatus = 200, array $headers = [])` сохраняет поля status/code/data. Code — прикладное значение, а не HTTP-статус; ID записи рекомендуется передавать внутри data.

`error(string $msg = '', int $httpStatus = 400, mixed $data = null, array $headers = [])` создаёт status=false и msg. Ненулевой data добавляется отдельным полем и должен содержать только публичные данные. Диагностику пишите через LogHelper.

`json(mixed $data, int $httpStatus = 200, array $headers = [])` принимает произвольное JSON-совместимое значение. JSON кодируется при создании объекта с JSON_THROW_ON_ERROR: некорректные данные вызывают исключение до отправки ответа. App обрабатывает его как обычную ошибку 500.

Content-Type всегда application/json; charset=utf-8. Дополнительные заголовки проверяются на недопустимые имена и управляющие символы. Content-Type, Content-Length и Transfer-Encoding задавать нельзя. Поддерживаются HTTP-статусы 200–599, кроме 204, 205 и 304, не допускающих JSON-тело. HEAD отправляет заголовки без тела. Ранее установленные Location и Content-Length удаляются; остальные заголовки (в том числе Set-Cookie) сохраняются.

Используйте throwSend() только внутри App::run(). Старый ручной bootstrap лендинга нужно сначала перевести на App; простой вызов Response::ok() больше не выводит ответ и не останавливает обработчик. В middleware также вызывайте ->throwSend(). Широкий catch(Throwable) в прикладном коде не должен поглощать внутренний ResponseReady — повторно выбрасывайте его. Прямой emit() отправляет ответ без завершения обработчика и без очистки буферов; обычно его вызывает только App.

Response не изменяет исторические форматы встроенных 404/500. Уже отправленные клиенту данные и незакрываемые буферы остаются ограничением App.

Явный httpStatus сохраняется и при пользовательском заголовке Location: он не заменяется неявным HTTP 302.
