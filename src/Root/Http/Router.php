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

    public static function init(string $request)
    {
        //$_SERVER['REQUEST_URI']
        self::$request = $request;
        self::make_Request_Route();
        self::make_Request_Params();
        self::make_Routes(Settings::get_Directory('routes'));
        self::make_Middlewares(Settings::get_Directory('middlewares'));
    }

    public static function route(string $name, array $new_Params = null): bool
    {
        foreach (self::$rules as $rule) {
            if ($rule['name'] == $name) {
                self::$path = $rule['path'];

                if (isset($rule['address'])) {
                    self::$address = $rule['address'];
                } else {
                    self::$address = $rule['name'];
                }

                if ($new_Params != null) {
                    self::$params = $new_Params;
                }

                if (isset($rule['middlewares'])) {
                    foreach ($rule['middlewares'] as $middleware) {
                        if (class_exists($middleware)) {
                            /** @var Middleware $middleware */
                            $middleware::apply();
                        }
                    }
                }

                if (isset($rule['wrapper'])) {
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

    public static function redirect(string $name, array $new_Params = null): void
    {
        $url = $name;
        if (isset($new_Params)) {
            $delimeter = '?';
            foreach ($new_Params as $key => $value) {
                $url .= $delimeter . $key . '=' . $value;
                $delimeter = '&';
            }
        }
        header('Location:https://' . Settings::get('host', 'domain') . Settings::get('host', 'host') . '/' . $url);
        exit;
    }

    private static function make_Request_Route(): void
    {
        self::$route = explode('?', trim(self::$request, '/'))[0];
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

    public static function get_Param_by_Name(string $name): string | bool
    {
        if (isset(self::$params[$name])) {
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

    private static function make_Request_Params(): void
    {
        $query = [];
        $parts = parse_url(trim(self::$request, '/'));
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
