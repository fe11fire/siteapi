<?php
require dirname(__DIR__) . '/src/Root/Helpers/SecretHelper.php';
use SiteApi\Root\Helpers\SecretHelper;
function check(bool $condition): void { if (!$condition) throw new RuntimeException('CSRF check failed'); }
session_save_path(sys_get_temp_dir());
check(session_status() === PHP_SESSION_NONE);
try {
    check(!SecretHelper::validate('missing', str_repeat('a', 64)));
    check(session_status() === PHP_SESSION_ACTIVE);
    $sessionId = session_id();
    $token = SecretHelper::get('form');
    check(session_id() === $sessionId);
    check(strlen($token) === 64 && $token === SecretHelper::get('form'));
    check(SecretHelper::validate('form', $token));
    check(!SecretHelper::validate('other', $token));
    check(SecretHelper::get('other') !== $token);
    foreach ([null, [], '', str_repeat('a', 64)] as $bad) check(!SecretHelper::validate('form', $bad));
    session_write_close();
    check(session_status() === PHP_SESSION_NONE);
    check(SecretHelper::validate('form', $token));
    check(session_id() === $sessionId);
    session_regenerate_id(true);
    check(SecretHelper::validate('form', $token));
    $_SESSION = [];
    check(!SecretHelper::validate('form', $token));
    check(SecretHelper::get('form') !== $token);
} finally {
    $_SESSION = [];
    session_destroy();
}
// Once output was sent, lazy startup must fail rather than bypass protection.
echo "Checking headers-sent failure\n";
try {
    SecretHelper::get('form');
    throw new LogicException('Expected startup failure after output');
} catch (RuntimeException $error) {
    check(str_contains($error->getMessage(), 'after response output'));
}
echo "CSRF session, random tokens and validation checks passed\n";
