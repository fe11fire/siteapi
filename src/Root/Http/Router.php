<?php

namespace SiteApi\Root\Http;

use Exception;
use SiteApi\Root\Helpers\LogHelper;
use SiteApi\Root\Settings\Settings;
use SiteApi\Root\Helpers\FileHelper;
use SiteApi\Root\Http\Contracts\Middleware;

class Router
{
    private static array $routes;
    private static array $rules = [];
    private static array $middleware_files;
    private static array $middlewares = [];
    private static string $request;
    private static string $route;
    private static string $path;
    private static string $address;
    private static array $params = [];

    /**
     * Очищает статическое состояние роутера для независимых тестовых сценариев.
     *
     * После сброса требуется init(); подключённые классы и эффект require_once не отменяются.
     */
    public static function reset(): void
    {
        self::$routes = [];
        self::$rules = [];
        self::$middleware_files = [];
        self::$middlewares = [];
        self::$request = '';
        self::$route = '';
        self::$path = '';
        self::$address = '';
        self::$params = [];
    }

    /**
     * Разбирает URI, загружает правила маршрутов и файлы middleware.
     *
     * Вызывается один раз на запрос: повторный init() без reset() накапливает правила.
     * В каталогах маршрутов и middleware подключаются все файлы без фильтра расширений.
     * @param string $request URI с путём и необязательной query string.
     * @param bool $allowMissingMiddlewares Разрешить отсутствие каталога middleware.
     * @throws \InvalidArgumentException Невозможно разобрать URI.
     * @throws \Throwable Ошибка чтения каталогов или выполнения конфигурационных PHP-файлов.
     */
    public static function init(string $request, bool $allowMissingMiddlewares = false)
    {
        self::$request = $request;
        self::make_Request_Route();
        self::make_Request_Params();
        self::make_Routes(Settings::get_Directory('routes'));
        $middlewares = Settings::get_Directory('middlewares');
        if (!$allowMissingMiddlewares || file_exists($middlewares)) {
            self::make_Middlewares($middlewares);
        }
    }

    /**
     * Находит маршрут по точному имени с префиксом env.folder и выполняет его.
     *
     * До страницы вызывает middleware; HTTP-метод автоматически не проверяет.
     * Wrapper получает $filename и сам подключает страницу. require_once не выполняет
     * общий wrapper или страницу повторно в том же запросе. Пути должны быть доверенными.
     * @param string $name Полное имя, например company/about при folder=company/.
     * @param array|null $new_Params null сохраняет параметры, массив полностью заменяет их.
     * @return bool true после выполнения; false при отсутствии правила.
     * @throws \RuntimeException Middleware отсутствует или не реализует контракт.
     * @throws \Throwable Исключение middleware, wrapper или страницы; ResponseReady пропускается в App.
     */
    public static function route(string $name, ?array $new_Params = null): bool
    {
        $folder = Settings::getString('env', 'folder', '');

        foreach (self::$rules as $rule) {

            if (($folder . $rule['name']) === $name) {
                self::$path = $folder . $rule['path'];

                if (isset($rule['address'])) {
                    self::$address = $rule['address'];
                } else {
                    self::$address = $folder . $rule['name'];
                }

                if ($new_Params !== null) {
                    self::$params = $new_Params;
                }

                if (isset($rule['middlewares'])) {
                    foreach ($rule['middlewares'] as $middleware) {
                        if (!is_string($middleware) || !class_exists($middleware)) {
                            throw new \RuntimeException('Middleware class not found: ' . (is_string($middleware) ? $middleware : get_debug_type($middleware)));
                        }
                        if (!is_subclass_of($middleware, Middleware::class)) {
                            throw new \RuntimeException('Middleware must implement ' . Middleware::class . ': ' . $middleware);
                        }
                        $middleware::apply();
                    }
                }

                if (isset($rule['wrapper'])) {
                    // echo $folder . $rule['wrapper'];
                    $filename = $rule['path'] . $rule['url']; //need for wrapper
                    require_once($rule['wrapper']);
                } else {
                    require_once($rule['path'] . $rule['url']);
                }

                return true;
            }
        }
        return false;
    }

    /**
     * Перенаправляет на маршрут приложения и завершает выполнение через exit.
     *
     * Адрес собирается как https://{env.domain}{env.host}/{env.folder}{name}.
     * Имя маршрута должно поступать из доверенного кода приложения.
     *
     * Устаревший способ сборки query string: ключи и значения new_Params
     * добавляются без URL-кодирования. Символы & и # меняют смысл значения;
     * существующие query string и фрагмент в name не учитываются при добавлении
     * параметров. Например, ['q' => 'a&sort=date'] создаст два параметра,
     * а redirect('search?page=2', ['q' => 'text']) добавит второй знак ?.
     * Для корректного кодирования и объединения параметров используйте redirectSafe().
     *
     * Важно: exit пропускает блоки finally, включая завершение App::run().
     * Это ограничение относится также к redirectSafe().
     *
     * @param string $name Маршрут относительно папки приложения.
     * @param array|null $new_Params Дополнительные параметры; значения не кодируются.
     * @return void Метод завершает выполнение процесса запроса.
     * @see self::redirectSafe()
     */
    public static function redirect(string $name, ?array $new_Params = null): void
    {
        $url = $name;
        if (isset($new_Params)) {
            $delimeter = '?';
            foreach ($new_Params as $key => $value) {
                $url .= $delimeter . $key . '=' . $value;
                $delimeter = '&';
            }
        }
        header('Location:https://' . Settings::getString('env', 'domain') . Settings::getString('env', 'host') . '/' . Settings::getString('env', 'folder', '') . $url);
        exit;
    }

    /**
     * Перенаправляет с URL-кодированием новых параметров по RFC 3986.
     *
     * Учитывает query string и фрагмент; null пропускается, массивы кодируются вложенно.
     * Сам маршрут и настройки адреса должны быть доверенными. Использует redirect(),
     * поэтому завершает запрос через exit и пропускает finally, включая App::run().
     * @param array|null $new_Params Дополнительные параметры URL.
     * @throws \InvalidArgumentException Имя маршрута содержит перевод строки.
     */
    public static function redirectSafe(string $name, ?array $new_Params = null): void
    {
        self::redirect(self::buildRedirectUrl($name, $new_Params));
    }

    private static function buildRedirectUrl(string $name, ?array $params): string
    {
        if (preg_match('/[\r\n]/', $name)) {
            throw new \InvalidArgumentException('Redirect route must not contain line breaks');
        }
        $parts = explode('#', $name, 2);
        $url = $parts[0];
        $query = http_build_query($params ?? [], '', '&', PHP_QUERY_RFC3986);
        if ($query !== '') {
            $separator = str_contains($url, '?') ? (str_ends_with($url, '?') || str_ends_with($url, '&') ? '' : '&') : '?';
            $url .= $separator . $query;
        }
        return $url . (isset($parts[1]) ? '#' . $parts[1] : '');
    }

    private static function make_Request_Route(): void
    {
        $parts = parse_url(self::$request);
        if ($parts === false) {
            throw new \InvalidArgumentException('Invalid request URI');
        }
        self::$route = trim($parts['path'] ?? '', '/');
    }

    /**
     * Возвращает путь запроса без query string и крайних слешей.
     *
     * Префикс env.folder остаётся в пути. До чтения требуется init() или reset().
     * @return string
     */
    public static function get_Route()
    {
        return self::$route;
    }

    /**
     * Возвращает путь выбранного маршрута с префиксом env.folder.
     *
     * До первого найденного маршрута значение не определено; reset() задаёт пустую строку.
     * @return string
     */
    public static function get_Path()
    {
        return self::$path;
    }

    /**
     * Возвращает address правила либо его имя с префиксом env.folder.
     *
     * До первого найденного маршрута значение не определено; reset() задаёт пустую строку.
     * @return string
     */
    public static function get_Address()
    {
        return self::$address;
    }

    /**
     * Возвращает параметры query string с учётом последующих замен.
     *
     * POST и JSON-тело не считываются; вложенные массивы parse_str() сохраняются.
     * @return array
     */
    public static function get_Params()
    {
        return self::$params;
    }

    /**
     * Возвращает параметр по имени.
     *
     * Отсутствие, явный null и значение false не различаются: результат false.
     * Для сохранения различий используйте get_Params() и array_key_exists().
     */
    public static function get_Param_by_Name(string $name): mixed
    {
        if (isset(self::$params) && isset(self::$params[$name])) {
            // LogHelper::log('get_Param_by_Name', ['Param' => $name, 'Value' => self::$params[$name]]);
            return self::$params[$name];
        }
        // LogHelper::log('Param is not isset', ['Param' => $name], 'errors/');
        return false;
    }

    /**
     * Создаёт или заменяет параметр без кодирования и изменения его типа.
     */
    public static function apply_Param_by_Name(string $name, mixed $value): void
    {
        self::$params[$name] = $value;
    }

    /**
     * Проверяет сегмент ajax после удаления префикса env.folder.
     *
     * Заголовок X-Requested-With не учитывается; до init()/reset() возвращает false.
     */
    public static function is_Ajax(): bool
    {
        return isset(self::$route) && RequestPath::isAjax(self::$route, Settings::getString('env', 'folder', ''));
    }

    private static function make_Request_Params(): void
    {
        $query = [];
        $parts = parse_url(self::$request);
        if ($parts === false) {
            throw new \InvalidArgumentException('Invalid request URI');
        }
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        // self::log('make_Request_Params', $query);
        self::$params = $query;
    }

    private static function make_Routes(string $routes_folder): void
    {
        self::$routes = FileHelper::list_Files($routes_folder);

        foreach (self::$routes as $route_file) {
            self::$rules = array_merge(self::$rules, include($routes_folder . '/' . $route_file));
        }
    }

    private static function make_Middlewares(string $middlewares_folder): void
    {
        try {
            self::$middleware_files = FileHelper::list_Files($middlewares_folder);
        } catch (Exception $e) {
            LogHelper::log('make_Middlewares error', ['error' => $e->getMessage()], 'errors/');
            return;
        }

        foreach (self::$middleware_files as $middlewares_file) {
            array_push(self::$middlewares, include($middlewares_folder . '/' . $middlewares_file));
        }
    }
}
