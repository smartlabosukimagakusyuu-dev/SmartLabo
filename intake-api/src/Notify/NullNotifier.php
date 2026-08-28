<?php
/**
 * HP Intake API — 何も送らない通知（HP-ONBOARDING-4H-R0 / SSOT v1.12 §9.11）。
 *
 * ★宛先・差出人が設定されていない環境の**既定**である（fail closed）。
 *   設定が無いまま「送ったつもり」にならないよう、`enabled()` は false を返す。
 * ★preflight（本番配置後の通し確認）でもこれを使い、**実メールを1通も出さない**。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Notify;

final class NullNotifier implements Notifier
{
    public function notifySubmitted(SubmissionNotice $notice): bool
    {
        // ★何もしない。true を返すと「送れた」と誤って監査へ残るため false を返す
        return false;
    }

    public function enabled(): bool
    {
        return false;
    }
}
