<?php
/**
 * HP Intake API — 通知の送り口（HP-ONBOARDING-4H-R0 / SSOT v1.12 §9.11）。
 *
 * ★業務コードから `mail()` を直接呼ばない。**この境界の向こう側だけ**が送る。
 *   こうしておくと
 *     - テストは実メールを1通も出さずに「送ったか」を確かめられる
 *     - `mail()` を使うファイルを**1つに限定**できる（静的検査で固定する）
 *     - 送信方式を変えるときに業務コードを触らずに済む
 *
 * ★送信の成否で**提出の成否を変えない**（§9.11-5）。
 *   戻り値は「送れたか」を呼び出し側が監査へ記録するためだけに使う。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Notify;

interface Notifier
{
    /**
     * 提出通知を送る。
     *
     * ★例外を外へ出さない。失敗は false で返す。
     *   通知の失敗で受付そのものを止めないためである。
     *
     * @return bool 送れたら true
     */
    public function notifySubmitted(SubmissionNotice $notice): bool;

    /** 実際に送る設定になっているか（管理画面・CLI の表示用） */
    public function enabled(): bool;
}
