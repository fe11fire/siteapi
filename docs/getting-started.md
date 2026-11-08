# Начало работы

[Обзор проекта](../readme.md)

## Среда

PHP >= 8.1; расширения `curl`, `PDO`, `pdo_mysql`, `memcached`, `mbstring`, `openssl`, `session`. Composer допускает совместимый polyfill для mbstring. Расширения проверяйте и в CLI, и в PHP, обслуживающем сайт.

Composer устанавливает Monolog, Carbon, PHPMailer и Predis. Расширения PHP и внешние серверы Composer не устанавливает. Для Redis расширение ext-redis не требуется.

## Установка библиотеки в приложение

В Composer-конфигурацию сайта добавьте репозиторий:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/fe11fire/siteapi.git"}
    ],
    "config": {
        "secure-http": true,
        "platform-check": true
    }
}
```

Затем:

```sh
composer require fellfire/siteapi:dev-main
vendor/bin/siteapi init .
composer check-platform-reqs
php -S localhost:8000 -t public public/index.php
```

`dev-main` означает установку ветки разработки; фиксируйте composer.lock приложения. Конфигурация Composer из зависимости не наследуется корневым проектом: проверки и HTTPS включаются в конфигурации самого сайта.

Если работаете непосредственно с исходниками библиотеки:

```sh
composer install
php bin/siteapi init .
php -S localhost:8000 -t public public/index.php
```

Не обходите отсутствующие расширения через `--ignore-platform-reqs`. Если проверка сообщает об отсутствии Memcached или другого расширения, установите его в используемую сборку PHP до установки зависимостей.

## Что создаёт генератор

```text
my-site/
├── public/index.php
├── routes/web.php
├── pages/home.php
└── vendor/                Уже установлен Composer
```

Существующие файлы не перезаписываются. При ошибке записи после начала генерации часть новых файлов может остаться. В минимальном приложении нет обязательных пустых каталогов настроек и middleware.

Точка входа:

```php
<?php
require dirname(__DIR__) . '/vendor/autoload.php';

SiteApi\App::run(dirname(__DIR__));
```

Маршруты:

```php
<?php
return [
    ['name' => '', 'path' => 'pages/', 'url' => 'home.php'],
    ['name' => 'about', 'path' => 'pages/', 'url' => 'about.php'],
];
```

Добавьте `pages/about.php`, чтобы открыть `/about`. Тела страниц — обычные PHP-файлы.

## Веб-сервер

Встроенный PHP-сервер выше предназначен для локального запуска динамических страниц. Генерируемая точка входа не содержит отдельного сценария раздачи статических файлов при использовании её как router script. Для полноценного сайта настройте раздачу статики веб-сервером.

В production публичный корень — `public/`; настройки, vendor, страницы и журналы находятся за его пределами. Запросы несуществующих статических путей направляются в index.php.

Пример Apache `.htaccess` внутри public/ (требует разрешённого mod_rewrite):

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

Пример расположения правил Nginx; пути и PHP upstream задаются конфигурацией сервера:

```nginx
root /absolute/path/to/my-site/public;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Отключите публичный вывод ошибок PHP; настройте серверный журнал ошибок. Дайте PHP права записи в каталог журналов и хранилище сессий. Не выводите данные до App::run().

## Когда нужны настройки

Создайте `settings/main` и JSON-файлы только для используемых возможностей. Например, `db.json` для MySQL. Окружение имеет приоритет, но `.env` не загружается автоматически.

Путь каталога настроек можно изменить до запуска:

```php
<?php
require dirname(__DIR__) . '/vendor/autoload.php';

SiteApi\Root\Settings\Settings::$directory = 'settings/production';
SiteApi\App::run(dirname(__DIR__));
```

Относительный путь разрешается от корня приложения.

[Настройки](configuration.md) · [Маршруты и middleware](http.md) · [Проверки](testing.md)
