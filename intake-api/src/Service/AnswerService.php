<?php
/**
 * HP Intake API — 回答の保存と提出（SSOT §2.3 / §3 / §6）。
 *
 *  - 受け付けるのは JSON 11分類のみ。**未知キーは拒否**
 *  - 楽観ロック（intake_answers.version）
 *  - 二重送信は status による冪等化で防ぐ（SSOT §6.4）
 *  - 提出履歴には**件数だけ**を残す（回答本文・項目名を残さない。SSOT §2.4）
 *  - 回答内容・PII をログへ出さない
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\Db;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Support\Clock;

final class AnswerService
{
    /** 配列分類の要素数上限（SSOT §3 の設計既定値） */
    public const ARRAY_CAPS = [
        'menus'          => 60,
        'staff'          => 30,
        'image_metadata' => 60,
    ];

    /** 1分類あたりのエンコード後の上限バイト（body 1MB の内訳として） */
    public const SECTION_MAX_BYTES = 262144;

    /** 提出に必要な条件。値ではなく**パスのみ**を返す */
    public const REQUIRED_PATHS = [
        'basic.legal_name', 'basic.operator_name', 'basic.postal_code', 'basic.address',
        'basic.access_text', 'basic.description', 'basic.payment_methods', 'basic.booking_methods',
        'business_hours.weekly', 'business_hours.closed_note',
        'menus', 'promotion.strengths', 'promotion.customer_profile', 'promotion.problems',
        'promotion.recommended_menus', 'promotion.concept',
        'design.template', 'design.tone', 'design.hero_message',
        'web_links.contact_methods',
        'image_metadata', 'rights.confirmations',
    ];

    /** 写真の最低枚数（SSOT §3.10 / 仕様書 §12.3） */
    public const MIN_IMAGES = 8;

    /** 法的確認の件数（L-01〜L-13） */
    public const RIGHTS_CONFIRMATIONS = 13;

    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly Audit $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function get(int $caseId): array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM intake_answers WHERE intake_case_id = :id');
        $stmt->execute([':id' => $caseId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new \RuntimeException('answers row missing');
        }

        $sections = [];
        foreach (Migrator::ANSWER_SECTIONS as $section) {
            $decoded            = json_decode((string)$row[$section . '_json'], true);
            $sections[$section] = is_array($decoded) ? $decoded : [];
        }

        return [
            'version'        => (int)$row['version'],
            'schema_version' => (int)$row['schema_version'],
            'sections'       => $sections,
            'updated_at'     => (string)$row['updated_at'],
        ];
    }

    /**
     * 途中保存（部分更新・楽観ロック）。
     * @param array<string,mixed> $sections
     * @return array{ok:bool,error?:string,version?:int}
     */
    public function save(int $caseId, array $sections, int $clientVersion): array
    {
        if ($sections === []) {
            return ['ok' => false, 'error' => 'bad_request'];
        }
        foreach ($sections as $name => $value) {
            if (!in_array($name, Migrator::ANSWER_SECTIONS, true)) {
                return ['ok' => false, 'error' => 'bad_request']; // 未知キーは拒否
            }
            if (!is_array($value)) {
                return ['ok' => false, 'error' => 'bad_request'];
            }
            if (isset(self::ARRAY_CAPS[$name]) && count($value) > self::ARRAY_CAPS[$name]) {
                return ['ok' => false, 'error' => 'bad_request']; // 上限超過は保存拒否（切り捨てない）
            }
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
            if ($encoded === false || strlen($encoded) > self::SECTION_MAX_BYTES) {
                return ['ok' => false, 'error' => 'payload_too_large'];
            }
        }

        $set    = ['version = version + 1', 'updated_at = :now'];
        $params = [':now' => $this->clock->iso(), ':id' => $caseId, ':version' => $clientVersion];
        foreach ($sections as $name => $value) {
            $set[]                      = $name . '_json = :' . $name;
            $params[':' . $name]        = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $stmt = $this->db->pdo()->prepare(
            'UPDATE intake_answers SET ' . implode(', ', $set) . '
              WHERE intake_case_id = :id AND version = :version'
        );
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'error' => 'conflict'];
        }

        $this->audit->record($caseId, 'answer_saved', 'ok');

        return ['ok' => true, 'version' => $clientVersion + 1];
    }

    /**
     * 提出に必要な条件を評価する。
     * ★不足は**パスのみ**を返す（値・氏名・連絡先を返さない）。
     * @return array{field_count:int,missing:list<string>}
     */
    public function evaluate(int $caseId): array
    {
        $s       = $this->get($caseId)['sections'];
        $missing = [];

        $filled = static function (mixed $v): bool {
            if (is_string($v)) {
                return trim($v) !== '';
            }
            if (is_array($v)) {
                return $v !== [];
            }

            return $v !== null;
        };

        $req = static function (string $path) use ($s, $filled, &$missing): void {
            $parts = explode('.', $path);
            $value = $s;
            foreach ($parts as $part) {
                if (!is_array($value) || !array_key_exists($part, $value)) {
                    $missing[] = $path;

                    return;
                }
                $value = $value[$part];
            }
            if (!$filled($value)) {
                $missing[] = $path;
            }
        };

        foreach (self::REQUIRED_PATHS as $path) {
            $req($path);
        }

        // 営業時間は7曜日ぶん
        $weekly = $s['business_hours']['weekly'] ?? null;
        if (is_array($weekly) && count($weekly) !== 7 && !in_array('business_hours.weekly', $missing, true)) {
            $missing[] = 'business_hours.weekly';
        }

        // 料金: 税区分 unknown / 税込総額なしは掲載できない（SSOT §3.3）
        foreach ((array)($s['menus'] ?? []) as $i => $menu) {
            if (!is_array($menu)) {
                $missing[] = 'menus';
                continue;
            }
            $type = $menu['price_type'] ?? 'fixed';
            if (in_array($type, ['fixed', 'from', 'free'], true) && !isset($menu['price_inc_tax'])) {
                $missing[] = 'menus[' . (int)$i . '].price_inc_tax';
            }
            if (($menu['tax_type'] ?? 'unknown') === 'unknown') {
                $missing[] = 'menus[' . (int)$i . '].tax_type';
            }
        }

        // 写真は最低8枚。権利確認が無いものは数えない（SSOT §3.10）
        $images = array_values(array_filter(
            (array)($s['image_metadata'] ?? []),
            static fn ($img): bool => is_array($img) && ($img['rights_confirmed'] ?? false) === true
        ));
        if (count($images) < self::MIN_IMAGES) {
            $missing[] = 'image_metadata.min_' . self::MIN_IMAGES;
        }
        foreach ($images as $i => $img) {
            $role = $img['role'] ?? 'other';
            if (in_array($role, ['staff', 'owner', 'treatment_scene'], true) && ($img['person_consent'] ?? false) !== true) {
                $missing[] = 'image_metadata[' . (int)$i . '].person_consent';
            }
        }

        // 掲載するスタッフは本人同意が必須（SSOT §3.4）
        foreach ((array)($s['staff'] ?? []) as $i => $st) {
            if (is_array($st) && ($st['published'] ?? false) === true && ($st['consent_agreed'] ?? false) !== true) {
                $missing[] = 'staff[' . (int)$i . '].consent_agreed';
            }
        }

        // 法的確認は13件すべて true（SSOT §3.11）
        $confirmations = (array)($s['rights']['confirmations'] ?? []);
        $agreed        = count(array_filter(
            $confirmations,
            static fn ($c): bool => is_array($c) && ($c['agreed'] ?? false) === true
        ));
        if ($agreed !== self::RIGHTS_CONFIRMATIONS) {
            $missing[] = 'rights.confirmations.all_agreed';
        }

        $fieldCount = 0;
        foreach ($s as $section) {
            $fieldCount += is_array($section) ? count($section) : 0;
        }

        return ['field_count' => $fieldCount, 'missing' => array_values(array_unique($missing))];
    }

    /**
     * 最終提出。
     * ★二重送信は status による冪等化で防ぐ（SSOT §6.4）。2回目は履歴を増やさない。
     * @return array{ok:bool,error?:string,already?:bool,missing?:list<string>,missing_count?:int}
     */
    public function submit(int $caseId, string $currentStatus): array
    {
        if (in_array($currentStatus, ['submitted', 'reviewed'], true)) {
            return ['ok' => true, 'already' => true];
        }
        if (!in_array($currentStatus, CaseService::EDITABLE, true)) {
            return ['ok' => false, 'error' => 'not_editable'];
        }

        $result   = $this->evaluate($caseId);
        $missing  = $result['missing'];
        $schemaV  = $this->get($caseId)['schema_version'];

        if ($missing !== []) {
            $this->recordHistory($caseId, 'submitted', $schemaV, 'validation_error', $result['field_count'], count($missing));
            $this->audit->record($caseId, 'submitted', 'validation_error');

            return ['ok' => false, 'error' => 'incomplete', 'missing' => $missing, 'missing_count' => count($missing)];
        }

        $this->recordHistory($caseId, 'submitted', $schemaV, 'ok', $result['field_count'], 0);
        $this->audit->record($caseId, 'submitted', 'ok');

        return ['ok' => true, 'already' => false];
    }

    public function recordHistory(
        int $caseId,
        string $eventType,
        int $schemaVersion,
        string $resultCode,
        ?int $fieldCount = null,
        ?int $missingCount = null,
    ): void {
        $this->db->pdo()->prepare(
            'INSERT INTO intake_submission_history
                (intake_case_id, event_type, schema_version, submitted_at, result_code, field_count, missing_count)
             VALUES (:case_id, :event_type, :schema_version, :submitted_at, :result_code, :field_count, :missing_count)'
        )->execute([
            ':case_id'        => $caseId,
            ':event_type'     => $eventType,
            ':schema_version' => $schemaVersion,
            ':submitted_at'   => $this->clock->iso(),
            ':result_code'    => $resultCode,
            ':field_count'    => $fieldCount,
            ':missing_count'  => $missingCount,
        ]);
    }

    public function historyCount(int $caseId): int
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*) FROM intake_submission_history WHERE intake_case_id = :id'
        );
        $stmt->execute([':id' => $caseId]);

        return (int)$stmt->fetchColumn();
    }
}
