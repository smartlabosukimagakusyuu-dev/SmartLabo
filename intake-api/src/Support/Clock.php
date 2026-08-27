<?php
/**
 * HP Intake API — 時刻。
 * テストで固定時刻・時刻送りができるよう、実時刻を直接呼ばずここへ集約する。
 * 日時は ISO 8601（SSOT §2.0-3）。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Support;

class Clock
{
    public function now(): int
    {
        return time();
    }

    public function iso(?int $ts = null): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $ts ?? $this->now());
    }

    public function isoAfter(int $seconds): string
    {
        return $this->iso($this->now() + $seconds);
    }

    /** ISO文字列が現在時刻より未来か */
    public function isFuture(?string $iso): bool
    {
        if ($iso === null || $iso === '') {
            return false;
        }
        $ts = strtotime($iso);

        return $ts !== false && $ts > $this->now();
    }
}
