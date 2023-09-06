<?php
namespace SiteApi\Root\Http\Models;

class Module
{
    private string $filename;
    private array $vars;

    private function __construct(string $filename, array $vars)
    {
        $this->filename = $filename;
        // echo 'Module ' . $filename . ' vars - ' . json_encode($vars);
        $this->vars = $vars;
    }

    public static function make(string $filename, array $vars = [])
    {
        return new Module($filename, $vars);
    }

    public function get_Filename()
    {
        return $this->filename;
    }

    public function get_Style_Filename()
    {
        return dirname($this->filename) . '/style_' . basename($this->filename);
    }

    public function get_Vars()
    {
        return $this->vars;
    }
}
