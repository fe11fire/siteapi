<?php

namespace SiteApi\Root\Helpers;

/** Session-bound CSRF tokens with lazy session initialization. */
class SecretHelper
{
    private const SESSION_KEY = '__siteapi_csrf_tokens';

    public static function get(string $name): string
    {
        self::ensureSession();
        if ($name === '') {
            throw new \InvalidArgumentException('CSRF token name must not be empty');
        }
        $tokens = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($tokens)) {
            $tokens = [];
        }
        if (!isset($tokens[$name]) || !is_string($tokens[$name]) || !preg_match('/\A[a-f0-9]{64}\z/', $tokens[$name])) {
            $tokens[$name] = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $tokens;
        }
        return $tokens[$name];
    }

    /** Does not generate a token when none was issued. */
    public static function validate(string $name, mixed $token): bool
    {
        self::ensureSession();
        if ($name === '' || !is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return false;
        }
        $tokens = $_SESSION[self::SESSION_KEY] ?? null;
        $expected = is_array($tokens) ? ($tokens[$name] ?? null) : null;
        return is_string($expected) && hash_equals($expected, $token);
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (session_status() === PHP_SESSION_DISABLED) {
            throw new \RuntimeException('PHP sessions are disabled');
        }
        if (headers_sent()) {
            throw new \RuntimeException('Cannot start CSRF session after response output');
        }
        // Handle startup failure through the application error handler, without raw PHP output.
        if (!@session_start() || session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Cannot start CSRF session; check session storage configuration');
        }
    }
}
