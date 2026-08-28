<?php
/**
 * HP Intake API — 提出通知の中身（HP-ONBOARDING-4H-R0 / SSOT v1.12 §9.11）。
 *
 * ★通知に載せてよいのは **3項目だけ**である。
 *     1. 案件番号
 *     2. イベント種別（`submitted` のみ）
 *     3. 発生日時（UTC。末尾に Z を付けて時間帯を明示する）
 *
 * ★載せてはいけないもの（1つでも入れない）:
 *   回答本文／店舗名／氏名／メールアドレス／電話番号／住所／
 *   token／token hash／session／submission_id／Drive URL／共有先メール／
 *   修正依頼本文／生IP／HMAC化IP／DBの内部ID／秘密値／書き出しJSON本文。
 *
 * ★本文はここで**組み立てきる**。呼び出し側から自由な文字列を受け取らない。
 *   受け取るのは案件番号と時刻だけで、どちらも形式を検査する。
 * ★HTML メールにしない（text/plain・UTF-8）。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Notify;

final class SubmissionNotice
{
    /** Phase 1 で通知するイベントはこれだけ */
    public const EVENT = 'submitted';

    /** 案件番号の形（SSOT §2.1）。ここを通らない値は通知しない */
    private const CASE_NUMBER_PATTERN = '/^HP-\d{4,6}-\d{4}$/';

    /** 発生日時の形（UTC・ISO 8601） */
    private const OCCURRED_AT_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    private function __construct(
        public readonly string $caseNumber,
        public readonly string $occurredAt,
    ) {
    }

    /**
     * 検査を通ったときだけ通知を作る。
     * ★通らなければ **null**（＝通知しない）。無理に送らない。
     */
    public static function forSubmitted(string $caseNumber, string $occurredAt): ?self
    {
        if (preg_match(self::CASE_NUMBER_PATTERN, $caseNumber) !== 1) {
            return null;
        }
        if (preg_match(self::OCCURRED_AT_PATTERN, $occurredAt) !== 1) {
            return null;
        }

        return new self($caseNumber, $occurredAt);
    }

    /** 件名。★固定形式。案件番号以外を入れない */
    public function subject(): string
    {
        return '[HP Intake] ' . self::EVENT . ' ' . $this->caseNumber;
    }

    /**
     * 本文。★allowlist の3項目だけを、固定の並びで書く。
     *   ここへ項目を足すときは SSOT §9.11 を改定してから行う。
     */
    public function body(): string
    {
        return implode("\n", [
            'HP Intake の受付システムからの通知です。',
            '',
            '案件番号 : ' . $this->caseNumber,
            'イベント : ' . self::EVENT,
            '発生日時 : ' . $this->occurredAt . '（UTC）',
            '',
            '内容の確認は内部確認画面から行ってください。',
            'このメールに回答内容・店舗情報は含まれていません。',
            '',
        ]);
    }
}
