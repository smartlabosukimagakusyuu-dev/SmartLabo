<?php
/**
 * HP Intake API — Drive フォルダURLの暗号化（SSOT §7.3）。
 *
 *  - AES-256-GCM（認証付き暗号）
 *  - IV は毎回ランダム、認証タグを併せて保存する
 *  - 保存形式: iv(12) || tag(16) || ciphertext
 *  - 鍵は private/intake-config.php または環境変数からのみ取得（Config が fail closed）
 *
 * ★暗号化は「DBファイルが単体で漏れた場合に共有先の入口を即座に晒さない」ための
 *   多層防御であり、アクセス制御の代わりではない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Support;

final class Crypto
{
    private const CIPHER  = 'aes-256-gcm';
    private const IV_LEN  = 12;
    private const TAG_LEN = 16;

    private string $key;

    public function __construct(string $rawKey)
    {
        // 設定値は任意長のため、鍵導出で 32 バイトへ正規化する
        $this->key = hash('sha256', 'intake-drive-url|' . $rawKey, true);
    }

    public function encrypt(string $plain): string
    {
        $iv  = random_bytes(self::IV_LEN);
        $tag = '';
        $ct  = openssl_encrypt($plain, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);
        if ($ct === false) {
            throw new \RuntimeException('encrypt failed');
        }

        return $iv . $tag . $ct;
    }

    public function decrypt(string $blob): ?string
    {
        if (strlen($blob) <= self::IV_LEN + self::TAG_LEN) {
            return null;
        }
        $iv  = substr($blob, 0, self::IV_LEN);
        $tag = substr($blob, self::IV_LEN, self::TAG_LEN);
        $ct  = substr($blob, self::IV_LEN + self::TAG_LEN);

        $plain = openssl_decrypt($ct, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? null : $plain;
    }
}
