<?php
/**
 * HP Intake API — 最小オートローダ（Composer を使わない）
 * SmartLabo\Intake\Foo\Bar → src/Foo/Bar.php
 */
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'SmartLabo\\Intake\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path     = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
