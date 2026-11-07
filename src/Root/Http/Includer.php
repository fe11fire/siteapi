<?php
namespace SiteApi\Root\Http;

use SiteApi\Root\Helpers\LogHelper;
use SiteApi\Root\Settings\Settings;
use SiteApi\Root\Http\Models\Module;

class Includer
{
    private static array $module_styles = [];

    public static function include_Headers(array $headers): void
    {
        if (count($headers) == 0) {
            return;
        }

        self::include_Headers_block($headers, 'css');
        self::include_Headers_block($headers, 'js');
        self::include_Headers_block($headers, 'views');
    }

    private static function include_Headers_block(array $headers, string $block)
    {
        foreach ($headers as $header) {
            if (file_exists(Settings::get_Directory('headers') . '/' . $header . '/' . $block . '.php')) {
                self::include_Module(Module::make(Settings::get_Directory('headers') . '/' . $header . '/' . $block . '.php'));
            }
        }
    }

    public static function include_Module(Module $module): void
    {
        $vars = $module->get_Vars();

        foreach ($vars as $key => $key_value) {
            $$key = $key_value;
        }

        $filename = $module->get_Filename();

        if (!in_array($filename, self::$module_styles)) {
            array_push(self::$module_styles, $filename);
            if (file_exists($module->get_Style_Filename())) {
                include($module->get_Style_Filename());
            }
        }
        if (!file_exists($module->get_Filename())) {
            LogHelper::log('No file ' . $module->get_Filename() . ' to include', [], 'errors/');
            return;
        }

        include($module->get_Filename());
    }

    private static function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function var_To_InlineData(array $data): string
    {
        $line = '';
        foreach ($data as $key => $key_value) {
            if (!preg_match('/\A[a-zA-Z0-9_][a-zA-Z0-9_.:-]*\z/', (string) $key)) {
                throw new \InvalidArgumentException('Invalid data attribute name');
            }
            $line .= ' data-' . $key . '="' . self::escapeHtml((string) $key_value) . '"';
        }
        return $line;
    }

    /** CSS values must come from trusted application code. */
    public static function var_To_InlineStyle(array $style): string
    {
        $declarations = '';
        foreach ($style as $key => $key_value) {
            if (!preg_match('/\A(?:--[a-zA-Z0-9_-]+|-?[a-zA-Z][a-zA-Z0-9-]*)\z/', (string) $key)) {
                throw new \InvalidArgumentException('Invalid CSS property name');
            }
            $declarations .= $key . ': ' . $key_value . '; ';
        }
        return 'style="' . self::escapeHtml($declarations) . '"';
    }

    public static function var_To_SelectOptions(array $data): string
    {
        $options = '';
        foreach ($data as $key) {
            $checked = '';
            if (isset($key['checked']) && ($key['checked'] == true)) {
                $checked = ' checked';
            }
            $selected = '';
            if (isset($key['selected']) && ($key['selected'] == true)) {
                $selected = ' selected';
            }
            $options .= '<option value="' . self::escapeHtml((string) $key['value']) . '"' . $checked . $selected . '>' . self::escapeHtml((string) $key['text']) . '</option>';
        }
        return $options;
    }

    /**
     * Build event attributes from trusted application JavaScript.
     * Escapes HTML attribute boundaries; does not sanitize JavaScript.
     *
     * @param array<string, string> $data Event names mapped to JavaScript bodies.
     */
    public static function var_To_InlineScripts(array $data): string
    {
        $events = '';
        foreach ($data as $event => $script) {
            if (!preg_match('/\Aon[a-zA-Z]+\z/', (string) $event)) {
                throw new \InvalidArgumentException('Invalid event attribute name');
            }
            if (!is_string($script)) {
                throw new \InvalidArgumentException('JavaScript body must be a string');
            }
            $events .= ' ' . $event . '="' . self::escapeHtml($script) . '"';
        }
        return $events;
    }
    /** Function names must refer to trusted application functions. */
    public static function var_To_InlineEvents(array $data): string
    {
        $events = '';
        foreach ($data as $key => $key_value) {
            if (!preg_match('/\Aon[a-zA-Z]+\z/', (string) $key)) {
                throw new \InvalidArgumentException('Invalid event attribute name');
            }
            if (!is_array($key_value) || !isset($key_value[0]) || !is_string($key_value[0]) ||
                !preg_match('/\A[a-zA-Z_$][a-zA-Z0-9_$]*(?:\.[a-zA-Z_$][a-zA-Z0-9_$]*)*\z/', $key_value[0])) {
                throw new \InvalidArgumentException('Invalid JavaScript function name');
            }
            $events .= ' ' . $key . '="' . self::escapeHtml($key_value[0] . '()') . '"';
        }
        return $events;
    }
}
