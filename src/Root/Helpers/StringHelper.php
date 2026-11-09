<?php

namespace SiteApi\Root\Helpers;

class StringHelper

{
    const ALPHABET_ONLY_DIGITS = 1;
    const ALPHABET_ONLY_CHARS = 2;
    const ALPHABET_ALL_SYMBOLS = 3;
    const UPPER_CASE_ALL = 1;
    const UPPER_CASE_NONE = 2;
    const UPPER_CASE_BOTH = 3;

    /**
     * Возвращает строку UTF-8 в нижнем регистре.
     */
    public static function down_Letters(string $str): string
    {
        return mb_convert_case($str, MB_CASE_LOWER, "UTF-8");
    }

    /**
     * Возвращает строку UTF-8 в верхнем регистре.
     */
    public static function up_Letters(string $str): string
    {
        return mb_convert_case($str, MB_CASE_UPPER, "UTF-8");
    }

    /**
     * Меняет регистр первого символа, оставляя остальную строку без изменений.
     *
     * Ожидает UTF-8; выделение первого символа использует текущую внутреннюю кодировку mbstring.
     */
    public static function up_First_Letter(string $str): string
    {
        return mb_convert_case(mb_substr($str, 0, 1), MB_CASE_TITLE, "UTF-8") . mb_substr($str, 1);
    }

    /**
     * Выбирает русскую форму слова по целому числу, включая отрицательные числа.
     *
     * @param array{0: string, 1: string, 2: string} $after Формы: один, два, пять.
     * @param bool $null_number true возвращает только слово; false добавляет число и разделитель.
     * @param string $delimeter Разделитель числа и формы слова.
     */
    public static function plural_Form(
        int $number,
        array $after,
        bool $null_number = false,
        string $delimeter = ' '
    ): string {
        $cases = [2, 0, 1, 1, 1, 2];
        // Take the remainder first: abs(PHP_INT_MIN) would become a float.
        $lastTwo = abs($number % 100);
        $last = abs($number % 10);
        $form = $after[($lastTwo > 4 && $lastTwo < 20) ? 2 : $cases[min($last, 5)]];
        return $null_number ? $form : $number . $delimeter . $form;
    }



    /**
     * Генерирует случайный ASCII-код через random_int().
     *
     * Недопустимая длина заменяется на 5; дефисы добавляются после каждых пяти символов
     * и не входят в length. Алфавит букв не включает строчную l.
     * @param array{length?: int, hyphens?: bool, alphabet?: int, uppercase?: int} $options
     *     Длина 1–100, дефисы и константы ALPHABET_* и UPPER_CASE_*.
     * @throws \Throwable Ошибка источника случайности.
     */
    public static function generate_Code(
        array $options = [
            'length' => 5,
            'hyphens' => false,
            'alphabet' => self::ALPHABET_ALL_SYMBOLS,
            'uppercase' => self::UPPER_CASE_BOTH,
        ]
    ): string {
        if (
            (!isset($options['length'])) ||
            (intval($options['length']) < 1) ||
            (intval($options['length']) > 100)
        ) {
            $options['length'] = 5;
        } else {
            $options['length'] = intval($options['length']);
        }

        if (!isset($options['hyphens'])) {
            $options['hyphens'] = false;
        }

        if (!isset($options['alphabet'])) {
            $options['alphabet'] = self::ALPHABET_ALL_SYMBOLS;
        }

        if (!isset($options['uppercase'])) {
            $options['uppercase'] = self::UPPER_CASE_BOTH;
        }

        $digits = '0123456789';
        $chars = 'abcdefghijkmnopqrstuvwxyz';

        $alphabet = '';

        switch ($options['uppercase']) {
            case self::UPPER_CASE_ALL:
                $alphabet .= self::up_Letters($chars);
                break;
            case self::UPPER_CASE_NONE:
                $alphabet .= $chars;
                break;
            default:
                $alphabet .= self::up_Letters($chars) . $chars;
                break;
        }

        switch ($options['alphabet']) {
            case self::ALPHABET_ONLY_DIGITS:
                $alphabet = $digits;
                break;
            case self::ALPHABET_ONLY_CHARS:
                break;
            default:
                $alphabet .= $digits;
                break;
        }

        $length_alphabet = (strlen($alphabet));

        $i = 0;
        $code = '';

        while ($i < $options['length']) {
            $num = random_int(0, $length_alphabet - 1);
            $tmp = substr($alphabet, $num, 1);
            $code = $code . $tmp;
            if (
                (($i % 5) == 4) &&
                ($options['hyphens']) &&
                ($i + 1 < $options['length'])
            ) {
                $code = $code . '-';
            }
            $i++;
        }

        return $code;
    }

    /**
     * Возвращает 32 шестнадцатеричных символа из 16 случайных байт.
     *
     * Для password_hash() отдельную соль создавать не требуется.
     * @throws \Throwable Ошибка источника случайности.
     */
    public static function salt(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Оборачивает каждую строку в start/end и объединяет без разделителя.
     *
     * HTML-экранирование не выполняется.
     * @param list<string> $text
     */
    public static function wrap_Array_of_String(array $text, string $start, string $end): string
    {
        /** @var string */
        $str = '';
        foreach ($text as $line) {
            $str .= $start . $line . $end;
        }
        return $str;
    }

    /**
     * Дополняет строку нулями слева до указанной длины в байтах.
     *
     * Не обрезает строку и не учитывает многобайтную длину символов.
     */
    public static function draw_Nulls_left(string $str, int $length): string
    {
        while (strlen($str) < $length) {
            $str = '0' . $str;
        }
        return $str;
    }

    /**
     * Проверяет is_numeric(), несмотря на историческое имя.
     *
     * Возвращает true также для целых чисел и экспоненциальной записи, а не только дробей.
     */
    public static function is_float(string $string): bool
    {
        return is_numeric($string);
    }
}
