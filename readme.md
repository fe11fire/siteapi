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
use SiteApi\Root\Settings\Settings;

/* Подключение пакета */
require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');

Settings::init(); /* Инициализация настроек framework */
Router::init($_SERVER['REQUEST_URI']); /* Получение запроса */

if (Router::route(Router::get_Route())) {
    /* Маршрут успешно завершен */
    return;
}

/* Ошибка. Исключение или маршрут не найден */

if (Router::is_Ajax()) {
    /* Проверка маршрута на ajax-запрос, возвращение ошибки из шаблонов framework */
    echo Templates::pageAjax();
    return;
}

if (Router::route('404')) {
    /* Возвращение страницы 404 */
    return;
}

/* Все пропало. Вывод 404 из шаблонов framework */

echo Templates::page404();
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
<br>

### Построение html-файлов
<br>

### Логирование
<br>

### Кэширование
<br>

### Работа с CURL
<br>

### Отправка сообщений (Telegram)
<br>

### Работа с СУБД
<br>

### Режимы разработки
<br>

