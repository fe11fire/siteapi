<?php

namespace SiteApi\Root\Helpers;

use stdClass;

class StringHelper

{
    const ALPHABET_ONLY_DIGITS = 1;
    const ALPHABET_ONLY_CHARS = 2;
    const ALPHABET_ALL_SYMBOLS = 3;
    const UPPER_CASE_ALL = 1;
    const UPPER_CASE_NONE = 2;
    const UPPER_CASE_BOTH = 3;

    public static function down_Letters(string $str): string
    {
        return mb_convert_case($str, MB_CASE_LOWER, "UTF-8");
    }

    public static function up_Letters(string $str): string
    {
        return mb_convert_case($str, MB_CASE_UPPER, "UTF-8");
    }

    public static function up_First_Letter(string $str): string
    {
        return mb_convert_case($str, MB_CASE_TITLE, "UTF-8");
    }

    public static function plural_Form(
        int $number,
        array $after,
        bool $null_number = false,
        string $delimeter = ' '
    ): string {
        $cases = array(2, 0, 1, 1, 1, 2);
        if ($null_number) {
            return $after[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
        } else {
            return $number . $delimeter . $after[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
        }
    }



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

        switch ($alphabet) {
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

        srand((float)microtime() * 1000000);
        $i = 0;
        $code = '';

        while ($i < $options['length']) {
            $num = rand() % $length_alphabet;
            $tmp = substr($alphabet, $num, 1);
            $code = $code . $tmp;
            if (
                (($i % 5) == 4) &&
                ($options['hyphens'])
            ) {
                $code = $code . '-';
            }
            $i++;
        }

        return $code;
    }

    public static function salt()
    {
        return substr(md5(uniqid()), -8);
    }

    public static function wrap_Array_of_String(array $text, string $start, string $end): string
    {
        /** @var string */
        $str = '';
        foreach ($text as $line) {
            $str .= $start . $line . $end;
        }
        return $str;
    }

    public static function draw_Nulls_left(string $str, int $length): string
    {
        while (strlen($str) < $length) {
            $str = '0' . $str;
        }
        return $str;
    }

    public static function is_float(string $string): bool
    {
        return is_numeric($string);
    }
}
