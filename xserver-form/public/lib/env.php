<?php
// 設定ファイル(private/config.php)の読み込み。
// public_html の外側に置かれた private/config.php を参照する。

function slw_load_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = __DIR__ . '/../../private/config.php';
    if (!file_exists($path)) {
        throw new RuntimeException('config.php not found. private/config.php.example をコピーして作成してください。');
    }

    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException('config.php must return an array.');
    }
    return $config;
}

function slw_config(string $key)
{
    $config = slw_load_config();
    if (!array_key_exists($key, $config)) {
        throw new RuntimeException('Missing config key: ' . $key);
    }
    return $config[$key];
}

function slw_config_or_null(string $key)
{
    $config = slw_load_config();
    return $config[$key] ?? null;
}
