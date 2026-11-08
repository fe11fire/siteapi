<?php
require dirname(__DIR__) . '/src/Root/Helpers/StringHelper.php';
use SiteApi\Root\Helpers\StringHelper as S;
function check(bool $value): void { if (!$value) throw new RuntimeException('Code generation check failed'); }
foreach ([S::UPPER_CASE_ALL, S::UPPER_CASE_NONE, S::UPPER_CASE_BOTH] as $uppercase) {
    foreach ([S::ALPHABET_ONLY_DIGITS, S::ALPHABET_ONLY_CHARS, S::ALPHABET_ALL_SYMBOLS] as $alphabet) {
        foreach ([1, 5, 6, 10, 100] as $length) {
            foreach ([false, true] as $hyphens) {
                $code = S::generate_Code(compact('length', 'hyphens', 'alphabet', 'uppercase'));
                $raw = str_replace('-', '', $code);
                check(strlen($raw) === $length);
                check(!str_ends_with($code, '-'));
                check(substr_count($code, '-') === ($hyphens ? intdiv($length - 1, 5) : 0));
                $chars = $uppercase === S::UPPER_CASE_ALL ? 'A-Z' : ($uppercase === S::UPPER_CASE_NONE ? 'a-z' : 'a-zA-Z');
                $pattern = $alphabet === S::ALPHABET_ONLY_DIGITS ? '0-9' : ($alphabet === S::ALPHABET_ONLY_CHARS ? $chars : $chars . '0-9');
                check(preg_match('/\A[' . $pattern . ']+\z/', $raw) === 1);
                if ($hyphens) {
                    $groups = explode('-', $code);
                    foreach (array_slice($groups, 0, -1) as $group) check(strlen($group) === 5);
                }
            }
        }
    }
}
check(strlen(S::generate_Code()) === 5);
check(strlen(S::generate_Code(['length' => 0])) === 5);
echo "Code alphabet, case, length and hyphen checks passed\n";
