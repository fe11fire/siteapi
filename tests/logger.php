<?php
/** Provider selection only: no dependencies or log writes required. */
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'SiteApi\\')) {
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
        if (is_file($file)) require $file;
    }
});
use SiteApi\Root\Providers\Logger\{LoggerProvider, LoggerEnum};
function check(bool $value): void { if (!$value) throw new RuntimeException('Logger check failed'); }
check(LoggerProvider::info() === 'file');
foreach (LoggerEnum::cases() as $logger) {
    LoggerProvider::init($logger);
    check(LoggerProvider::info() === $logger->value);
    LoggerProvider::init($logger->value);
    check(LoggerProvider::info() === $logger->value);
}
$previous = LoggerProvider::info();
try {
    LoggerProvider::init('files');
    throw new LogicException('Expected invalid logger');
} catch (InvalidArgumentException $error) {
    check(str_contains($error->getMessage(), 'files'));
    foreach (LoggerEnum::cases() as $logger) check(str_contains($error->getMessage(), $logger->value));
}
check(LoggerProvider::info() === $previous);
echo "Logger enum, string, default and invalid configuration checks passed\n";
