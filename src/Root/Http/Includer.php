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

    public static function var_To_InlineData(array $data): string
    {
        $line = '';
        foreach ($data as $key => $key_value) {
            $line .= ' data-' . $key . '="' . $key_value . '"';
        }
        return $line;
    }

    public static function var_To_InlineStyle(array $style): string
    {
        $line = 'style="';
        foreach ($style as $key => $key_value) {
            $line .= $key . ': ' . $key_value . '; ';
        }
        $line .= '"';
        return $line;
    }

    public static function var_To_SelectOptions(array $data): string
    {
        $options = '';
        foreach ($data as $key) {
            //TODO
            $checked = '';
            if (isset($key['checked']) && ($key['checked'] == true)) {
                $checked = ' checked';
            }
            $selected = '';
            if (isset($key['selected']) && ($key['selected'] == true)) {
                $selected = ' selected';
            }
            $options .= '<option value="' . $key['value'] . '"' . $checked . $selected . '>' . $key['text'] . '</option>';
        }
        return $options;
    }

    public static function var_To_InlineEvents(array $data): string
    {
        $events = '';
        foreach ($data as $key => $key_value) {
            $events .= ' ' . $key . '="' . $key_value[0] . '()"';
        }
        return $events;
    }
}
