<?php
/**
 * HP Intake API — Drive フォルダURLと共有先メールの検査（SSOT v1.5 §7.3）。
 *
 * ★店舗の画面へ出すものなので、**受け取る時点で厳しく絞る**。
 *   表示側でどれだけ気をつけても、変なものを保存してしまえば後から効かない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Support;

final class DriveLink
{
    /** 受け入れるホスト（SSOT §7.3-2）。これ以外は短縮URLも含めて拒否 */
    public const ALLOWED_HOSTS = [
        'drive.google.com',
        'docs.google.com',
        'drive.usercontent.google.com',
    ];

    /** URL の長さ上限（§3.7 の URL 上限に合わせる） */
    public const URL_MAX = 500;

    /** 共有先メールの長さ上限（§3.1 の email 上限に合わせる） */
    public const EMAIL_MAX = 254;

    /**
     * Drive フォルダURLとして受け入れてよいか。
     *
     * @return array{ok:bool,error?:string,url?:string}
     */
    public static function checkUrl(mixed $raw): array
    {
        if (!is_string($raw)) {
            return ['ok' => false, 'error' => 'invalid'];
        }
        $url = trim($raw);

        if ($url === '') {
            return ['ok' => false, 'error' => 'empty'];
        }
        if (strlen($url) > self::URL_MAX) {
            return ['ok' => false, 'error' => 'too_long'];
        }
        // 制御文字・空白・改行を含まないこと（§7.3-6）
        if (preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return ['ok' => false, 'error' => 'invalid'];
        }
        if (!mb_check_encoding($url, 'UTF-8')) {
            return ['ok' => false, 'error' => 'invalid'];
        }
        // https のみ（§7.3-1）。scheme は大文字小文字を問わず判定する
        if (stripos($url, 'https://') !== 0) {
            return ['ok' => false, 'error' => 'scheme'];
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return ['ok' => false, 'error' => 'invalid'];
        }
        // userinfo を含まないこと（§7.3-3）。ホストの偽装に使われる
        if (isset($parts['user']) || isset($parts['pass'])) {
            return ['ok' => false, 'error' => 'userinfo'];
        }
        // ポート指定を含まないこと（§7.3-5）
        if (isset($parts['port'])) {
            return ['ok' => false, 'error' => 'port'];
        }
        if (($parts['scheme'] ?? '') !== 'https') {
            return ['ok' => false, 'error' => 'scheme'];
        }

        $host = strtolower($parts['host']);
        if (!in_array($host, self::ALLOWED_HOSTS, true)) {
            return ['ok' => false, 'error' => 'host'];
        }

        // query / fragment は保持してよい（?usp=sharing 等は正当。§7.3-7）
        return ['ok' => true, 'url' => $url];
    }

    /**
     * 共有先メールとして受け入れてよいか。
     *
     * @return array{ok:bool,error?:string,email?:string}
     */
    public static function checkEmail(mixed $raw): array
    {
        if (!is_string($raw)) {
            return ['ok' => false, 'error' => 'invalid'];
        }
        $email = trim($raw);

        if ($email === '') {
            return ['ok' => false, 'error' => 'empty'];
        }
        if (strlen($email) > self::EMAIL_MAX) {
            return ['ok' => false, 'error' => 'too_long'];
        }
        if (preg_match('/[\x00-\x20\x7F]/', $email) === 1) {
            return ['ok' => false, 'error' => 'invalid'];
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        return ['ok' => true, 'email' => $email];
    }
}
