<?php
/**
 * HP Intake API — token / session secret の生成・照合。
 *
 * SSOT §4.1 / §2.6:
 *  - random_bytes(32) → base64url 43文字（パディングなし）
 *  - DBへは SHA-256 の16進64文字のみを保存する。平文を保存する場所を作らない
 *  - 照合は hash_equals()（定数時間比較）
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Support;

final class Secret
{
    /** base64url 43文字（= 32バイト） */
    public const PATTERN = '/^[A-Za-z0-9_-]{43}$/';

    /** 見つからなかった場合に比較へ使うダミー（応答時間差で存在有無を漏らさない） */
    private const DUMMY_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /** 平文を生成する。★戻り値を保存・記録してはならない（Cookie / 発行時表示のみ） */
    public static function generate(): string
    {
        return self::toBase64Url(random_bytes(32));
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function isWellFormed(string $plain): bool
    {
        return preg_match(self::PATTERN, $plain) === 1;
    }

    /** 定数時間比較。$storedHash が null でも同じだけ時間を使う */
    public static function matches(string $plain, ?string $storedHash): bool
    {
        $candidate = self::hash($plain);
        $target    = $storedHash ?? self::DUMMY_HASH;
        $result    = hash_equals($target, $candidate);

        return $storedHash !== null && $result;
    }

    private static function toBase64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
