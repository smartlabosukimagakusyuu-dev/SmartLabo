<?php
/**
 * HP Intake API — 検証済み書き出し（SSOT v1.4 §11.3）。
 *
 * Operations（OPS-4）が最初に取り込む形。**API 接続はしない**。
 * 管理画面から案件単位でダウンロードする JSON ファイルだけを受け渡しに使う。
 *
 * ★allowlist 方式で組み立てる。DBの行をそのまま渡さない。
 * ★含めない: token / token_hash / session secret / session_hash / CSRF /
 *   生IP / ip_hmac / Cookie / 暗号鍵 / password hash / rate limit /
 *   **Drive URL（暗号文・平文とも）** / **DBの内部ID** /
 *   Stripe / Operations / AI Sales / 内部ログ / 監査明細
 * ★JSON 本文をログへ出さない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\Db;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Support\Clock;

final class ExportService
{
    /** 書き出し形式の版。★回答スキーマ版とは別物 */
    public const EXPORT_SCHEMA_VERSION = 1;

    /** 出典。取込側が判別に使う */
    public const SOURCE = 'hp_intake';

    /** 書き出してよい案件の状態（未提出は出さない。SSOT §11.3-5） */
    public const EXPORTABLE = ['submitted', 'reviewed', 'locked', 'closed'];

    /**
     * `intake_cases` から書き出してよい列だけ（allowlist）。
     * ★`id` / `drive_folder_url_enc` / `drive_folder_label` を**入れない**。
     */
    private const CASE_FIELDS = [
        'case_number', 'contract_type', 'status',
        'submitted_at', 'locked_at', 'closed_at',
        'drive_upload_confirmed_at', 'retention_delete_due',
    ];

    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly CaseService $cases,
        private readonly AnswerService $answers,
    ) {
    }

    /**
     * 案件1件を書き出す。
     *
     * ★呼び出し側の判断に頼らず、**ここでもう一度**状態と必須条件を検証する
     *   （SSOT §11.3-5）。
     *
     * @return array{ok:bool,error?:string,json?:string,file_name?:string,sha256?:string}
     */
    public function export(int $caseId): array
    {
        $case = $this->cases->find($caseId);
        if ($case === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $status = (string)$case['status'];
        if (!in_array($status, self::EXPORTABLE, true)) {
            // 未提出（draft / needs_revision）は書き出さない
            return ['ok' => false, 'error' => 'not_exportable'];
        }

        // 書き出す直前の再検証。必須が欠けた内容を「検証済み」として出さない
        $evaluation = $this->answers->evaluate($caseId);
        if ($evaluation['missing'] !== []) {
            return ['ok' => false, 'error' => 'incomplete'];
        }

        $payload = $this->buildPayload($case, $caseId, $evaluation['field_count']);

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        if ($json === false) {
            return ['ok' => false, 'error' => 'encode_failed'];
        }

        return [
            'ok'        => true,
            'json'      => $json,
            'file_name' => $this->fileName((string)$case['case_number']),
            'sha256'    => hash('sha256', $json),
        ];
    }

    /**
     * allowlist で組み立てる。
     * @param array<string,mixed> $case
     * @return array<string,mixed>
     */
    private function buildPayload(array $case, int $caseId, int $fieldCount): array
    {
        $answers = $this->answers->get($caseId);

        $out = [
            'export_schema_version' => self::EXPORT_SCHEMA_VERSION,
            'source'                => self::SOURCE,
            'generated_at'          => $this->clock->iso(),
        ];

        // 案件メタ（allowlist の列だけ）
        foreach (self::CASE_FIELDS as $field) {
            $out[$field] = $case[$field] === null ? null : (string)$case[$field];
        }

        // reviewed_at は列が無い。履歴の reviewed 行から導く（SSOT §11.3）
        $out['reviewed_at']            = $this->latestHistoryAt($caseId, 'reviewed');
        $out['revision_requested_at']  = $this->latestHistoryAt($caseId, 'revision_requested');

        $out['answer_schema_version'] = (int)$answers['schema_version'];

        // JSON 11分類。★分類名は Migrator::ANSWER_SECTIONS のものだけ
        $sections = [];
        foreach (Migrator::ANSWER_SECTIONS as $section) {
            $sections[$section] = $answers['sections'][$section] ?? null;
        }
        $out['answers'] = $sections;

        // 権利・同意は証跡として明示的にも置く（SSOT §11.3）
        $out['rights'] = $answers['sections']['rights'] ?? null;

        $out['submission_summary'] = $this->submissionSummary($caseId, $fieldCount);

        return $out;
    }

    /** 履歴から、指定イベントの最も新しい時刻を取る（無ければ null） */
    private function latestHistoryAt(int $caseId, string $eventType): ?string
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT submitted_at FROM intake_submission_history
              WHERE intake_case_id = :id AND event_type = :event AND result_code = :ok
              ORDER BY submitted_at DESC, id DESC
              LIMIT 1'
        );
        $stmt->execute([':id' => $caseId, ':event' => $eventType, ':ok' => 'ok']);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string)$value;
    }

    /**
     * 提出履歴の概要。
     * ★件数と最終結果だけ。**submission_id を含めない**（操作の識別子であり取込に不要）。
     * @return array<string,mixed>
     */
    private function submissionSummary(int $caseId, int $fieldCount): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT event_type, submitted_at, result_code, field_count, missing_count
               FROM intake_submission_history
              WHERE intake_case_id = :id AND event_type = :event AND result_code = :ok
              ORDER BY submitted_at DESC, id DESC
              LIMIT 1'
        );
        $stmt->execute([':id' => $caseId, ':event' => 'submitted', ':ok' => 'ok']);
        $last = $stmt->fetch();

        return [
            'total_count'   => $this->answers->historyCount($caseId),
            'last_submitted_at' => $last === false ? null : (string)$last['submitted_at'],
            'result_code'   => $last === false ? null : (string)$last['result_code'],
            'field_count'   => $last === false ? $fieldCount : (int)$last['field_count'],
            'missing_count' => $last === false ? 0 : (int)$last['missing_count'],
        ];
    }

    /** 案件番号を安全に正規化したファイル名（SSOT §11.3-2） */
    public function fileName(string $caseNumber): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $caseNumber) ?? '';
        $safe = trim($safe, '._-');
        if ($safe === '') {
            $safe = 'case';
        }

        return $safe . '_intake_export.json';
    }
}
