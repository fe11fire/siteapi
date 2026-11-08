<?php
namespace SiteApi\Root\Helpers {
    class LogHelper {
        public static array $entries = [];
        public static bool $fail = false;
        public static function log($message, $context, $folder): void {
            if (self::$fail) throw new \RuntimeException('logger failed');
            self::$entries[] = [$message, $context, $folder];
        }
        public static function error($message, $context): void { self::log($message, $context, 'errors/'); }
    }
}
namespace {
    spl_autoload_register(function($class) {
        if (str_starts_with($class, 'SiteApi\\')) require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
    });
    use SiteApi\Root\Helpers\{DBHelper, LogHelper};
    function check(bool $value): void { if (!$value) throw new RuntimeException('Safe SQL logging check failed'); }
    $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    (new ReflectionProperty(DBHelper::class, 'connection'))->setValue(null, $pdo);
    $pdo->exec('CREATE TABLE users(id INTEGER PRIMARY KEY, password TEXT UNIQUE)');
    DBHelper::insert('users', ['password' => 'PRIVATE_BOUND_VALUE']);
    DBHelper::select('*', ['users'], '', ['password' => 'PRIVATE_BOUND_VALUE'], '', $rows, true, true);
    DBHelper::update('users', ['password' => 'PRIVATE_UPDATED_VALUE'], ['id' => 1], true, true);
    DBHelper::selectCount('*', ['users'], '', ['password' => 'PRIVATE_UPDATED_VALUE'], '', true, true);
    DBHelper::delete('users', ['password' => 'PRIVATE_UPDATED_VALUE'], true, true);
    $error = new PDOException('PRIVATE_EXCEPTION_MESSAGE');
    $error->errorInfo = ['23000', 1062, 'PRIVATE_DRIVER_MESSAGE'];
    foreach ([$error, new RuntimeException('PRIVATE_WRAPPER_MESSAGE', 0, $error)] as $failure) {
        ob_start();
        \SiteApi\Root\Http\ErrorHandler::handle($failure);
        $response = ob_get_clean();
        check(!str_contains($response, 'PRIVATE_'));
    }
    try {
        DBHelper::select('*', ['missing'], '', [], '', $rows);
        throw new LogicException('Expected PDO exception');
    } catch (PDOException) {}
    check(!str_contains(json_encode(LogHelper::$entries), 'PRIVATE_'));
    foreach (LogHelper::$entries as [$message, $context, $folder]) {
        if ($folder === 'queries/') check(!isset($context['sql']));
        check(!isset($context['args']) && !isset($context['Set']) && !isset($context['where']));
    }
    $log = tempnam(sys_get_temp_dir(), 'siteapi-fallback-');
    $previous = ini_get('error_log');
    try {
        ini_set('error_log', $log);
        LogHelper::$fail = true;
        DBHelper::insert('users', ['password' => 'PRIVATE_FALLBACK_VALUE']);
        check((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1);
        ob_start();
        \SiteApi\Root\Http\ErrorHandler::handle($error);
        ob_end_clean();
        check(!str_contains(file_get_contents($log), 'PRIVATE_'));
    } finally {
        ini_set('error_log', $previous);
        unlink($log);
    }
    echo "Safe SQL logs, PDO propagation, ErrorHandler and fallback checks passed\n";
}
