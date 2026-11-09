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

    /**
     * Создаёт описание PHP-шаблона и его переменных, не подключая файл.
     *
     * Путь должен быть доверенным; существование файла не проверяется. Ключи module,
     * vars, filename, key, key_value конфликтуют со служебными переменными Includer.
     * @param array<string, mixed> $vars Переменные шаблона без зарезервированных имён.
     * @return self
     */
    public static function make(string $filename, array $vars = [])
    {
        return new Module($filename, $vars);
    }

    /**
     * Возвращает исходный путь шаблона без нормализации и проверки.
     * @return string
     */
    public function get_Filename()
    {
        return $this->filename;
    }

    /**
     * Возвращает путь style_{имя шаблона} в том же каталоге.
     *
     * Наличие файла оформления не проверяется.
     * @return string
     */
    public function get_Style_Filename()
    {
        return dirname($this->filename) . '/style_' . basename($this->filename);
    }

    /**
     * Возвращает данные для распаковки в переменные шаблона.
     * @return array<string, mixed>
     */
    public function get_Vars()
    {
        return $this->vars;
    }
}
