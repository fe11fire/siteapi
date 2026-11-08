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

    /** Clear router state between isolated tests; call init() before routing again. */
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

    public static function init(string $request)
    {
        self::$request = $request;
        self::make_Request_Route();
        self::make_Request_Params();
        self::make_Routes(Settings::get_Directory('routes'));
        self::make_Middlewares(Settings::get_Directory('middlewares'));
    }

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

    /** Encodes added query values; the route itself must be trusted application code. */
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

    public static function get_Route()
    {
        return self::$route;
    }

    public static function get_Path()
    {
        return self::$path;
    }

    public static function get_Address()
    {
        return self::$address;
    }

    public static function get_Params()
    {
        return self::$params;
    }

    public static function get_Param_by_Name(string $name): mixed
    {
        if (isset(self::$params) && isset(self::$params[$name])) {
            // LogHelper::log('get_Param_by_Name', ['Param' => $name, 'Value' => self::$params[$name]]);
            return self::$params[$name];
        }
        // LogHelper::log('Param is not isset', ['Param' => $name], 'errors/');
        return false;
    }

    public static function apply_Param_by_Name(string $name, string $value): void
    {
        if (!isset(self::$params[$name])) {
            self::$params[$name] = '';
        } else {
            self::$params[$name] .= '<br>';
        }
        self::$params[$name] .= $value;
    }

    public static function is_Ajax(): bool
    {
        return isset(self::$route) && explode('/', self::$route)[0] == 'ajax';
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
