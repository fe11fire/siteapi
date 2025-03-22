<?php

class Templates
{
    public static function pageAjax(): string
    {
        return json_encode([0, 1, 'Ошибка при отправке данных']);
    }

    public static function page404(): string
    {
        return '<div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;"><div><b>Page 404 not found!</b></div></div>';
    }
}
