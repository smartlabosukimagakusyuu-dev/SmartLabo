<?php
/**
 * HP Intake API — レート制限（SSOT §0.2 / §10.2）。
 *
 *  無効token試行 : HMAC化IP単位で 10分 5回
 *  有効案件の保存 : session/token ＋ HMAC化IP単位で 10分 60回
 *  最終提出       : 10分 5回
 *
 * ★生IPを DB・ログ・ファイル名のいずれへも保存しない。HMAC 化した値だけを使う。
 * ★HMAC鍵は Config が fail closed で保証する（未設定なら起動しない）。
 * ★保存先は public_html の外（private/ratelimit/）。保存するのは送信時刻のみ。
 *
 * 実装方式は contact-api/public/lib/security.php の運用実績にならう
 * （ファイル方式。SSOT の6テーブルへ7つ目を足さないため）。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Support\Clock;

final class RateLimiter
{
    public function __construct(
        private readonly Config $config,
        private readonly Clock $clock,
    ) {
    }

    /** 生IPを HMAC 化する。復元できないため保存しても個人特定に使えない */
    public function ipHmac(string $ip): string
    {
        return substr(hash_hmac('sha256', $ip, $this->config->ipHmacKey), 0, 32);
    }

    /**
     * 1回分を記録し、上限内なら true を返す。
     * @param string $bucket Config::RATE_LIMITS のキー
     * @param string $identity ipHmac、または ipHmac . ':' . session/token 由来の識別子
     */
    public function allow(string $bucket, string $identity): bool
    {
        if (!isset(Config::RATE_LIMITS[$bucket])) {
            return false; // 未定義バケットは fail closed
        }
        [$limit, $window] = Config::RATE_LIMITS[$bucket];

        $dir = $this->config->rateLimitDir;
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return false; // 記録できないなら通さない（fail closed）
        }

        $file = $dir . '/' . hash('sha256', $bucket . '|' . $identity);
        $now  = $this->clock->now();

        $stamps = [];
        if (is_file($file)) {
            $raw = (string)file_get_contents($file);
            foreach (explode("\n", $raw) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $ts = (int)$line;
                if ($ts > $now - $window) {
                    $stamps[] = $ts;
                }
            }
        }

        if (count($stamps) >= $limit) {
            // 上限に達している場合も、期限切れを削った内容へ書き戻す
            file_put_contents($file, implode("\n", $stamps), LOCK_EX);
            @chmod($file, 0600);

            return false;
        }

        $stamps[] = $now;
        file_put_contents($file, implode("\n", $stamps), LOCK_EX);
        @chmod($file, 0600);

        return true;
    }

    /** 1時間以上更新の無い記録を片付ける */
    public function cleanup(): void
    {
        $dir = $this->config->rateLimitDir;
        if (!is_dir($dir)) {
            return;
        }
        $threshold = $this->clock->now() - 3600;
        foreach ((array)glob($dir . '/*') as $path) {
            if (is_string($path) && is_file($path) && (int)filemtime($path) < $threshold) {
                @unlink($path);
            }
        }
    }
}
