<?php
/**
 * HP Intake API — テストランナー
 *
 * 実行:
 *   php -c intake-api/dev/php.ini intake-api/tests/run-tests.php
 *   終了コード 0 = 全件成功 / 1 = 失敗あり
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// 実行前にテスト用一時領域を掃除する（前回の残骸を持ち越さない）
$tmp = tmpDir();
$it  = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $path) {
    $path->isDir() ? @rmdir($path->getPathname()) : @unlink($path->getPathname());
}

foreach (glob(__DIR__ . '/test-*.php') as $file) {
    require_once $file;
}

echo "HP Intake API — テスト\n";
echo str_repeat('-', 64) . "\n";

foreach (TestRunner::$tests as $t) {
    try {
        ($t['fn'])();
        ++TestRunner::$passed;
    } catch (Throwable $e) {
        TestRunner::$failed[] = $t['name'] . ' :: ' . $e->getMessage();
    }
}

$total = TestRunner::$passed + count(TestRunner::$failed);
printf("  実行 %d件 / 成功 %d件 / 失敗 %d件\n", $total, TestRunner::$passed, count(TestRunner::$failed));

if (TestRunner::$failed !== []) {
    echo "\n[NG] 失敗したテスト\n";
    foreach (TestRunner::$failed as $name) {
        echo '  - ' . $name . "\n";
    }
    exit(1);
}

echo "\n[OK] すべて成功しました\n";
exit(0);
