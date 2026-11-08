<?php
require dirname(__DIR__) . '/src/Root/Helpers/StringHelper.php';
use SiteApi\Root\Helpers\StringHelper;
$forms = ['товар', 'товара', 'товаров'];
foreach ([0 => 2, 1 => 0, 2 => 1, 4 => 1, 5 => 2, 11 => 2, 12 => 2, 14 => 2, 20 => 2, 21 => 0, 22 => 1, 25 => 2, 101 => 0, 111 => 2] as $number => $index) {
    foreach ([$number, -$number] as $value) {
        if (StringHelper::plural_Form($value, $forms) !== $value . ' ' . $forms[$index]) throw new RuntimeException('Wrong plural: ' . $value);
        if (StringHelper::plural_Form($value, $forms, true) !== $forms[$index]) throw new RuntimeException('Wrong form-only result');
    }
}
foreach ([PHP_INT_MIN, PHP_INT_MAX] as $number) {
    $digits = ltrim((string) $number, '-');
    $lastTwo = (int) substr($digits, -2);
    $last = (int) substr($digits, -1);
    $index = $lastTwo >= 11 && $lastTwo <= 14 ? 2 : ($last === 1 ? 0 : ($last >= 2 && $last <= 4 ? 1 : 2));
    if (StringHelper::plural_Form($number, $forms) !== $number . ' ' . $forms[$index]) throw new RuntimeException('Integer boundary failed');
}
if (StringHelper::plural_Form(-2, $forms, false, ': ') !== '-2: товара') throw new RuntimeException('Delimiter failed');
echo "Positive, negative, zero and integer boundary plural checks passed\n";
