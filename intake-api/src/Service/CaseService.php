<?php
/**
 * HP Intake API — 案件（SSOT §2.1 / §5）。
 *
 * 状態遷移（SSOT §5.1）:
 *   draft → submitted →（needs_revision → submitted）/（reviewed）→ locked → closed
 *   任意 → closed（中止案件）
 *
 * ★locked / closed へ移した時点で token と session をすべて失効させる（SSOT §5.2）。
 * ★公開承認はこの状態遷移に含めない。正式記録は Smart Labo Operations（SSOT §5.4）。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\Db;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Support\Clock;
use SmartLabo\Intake\Support\Crypto;

final class CaseService
{
    public const STATUSES = ['draft', 'submitted', 'needs_revision', 'reviewed', 'locked', 'closed'];

    /** 店舗が入力・提出できる状態 */
    public const EDITABLE = ['draft', 'needs_revision'];

    /** 店舗が閲覧できる状態（locked / closed は token/session ごと失効させる） */
    public const READABLE = ['draft', 'submitted', 'needs_revision', 'reviewed'];

    /** 許可する遷移（これ以外は行わない。SSOT §5.1） */
    private const TRANSITIONS = [
        'draft'          => ['submitted', 'closed'],
        'submitted'      => ['needs_revision', 'reviewed', 'closed'],
        'needs_revision' => ['submitted', 'closed'],
        'reviewed'       => ['locked', 'closed'],
        'locked'         => ['closed'],
        'closed'         => [],
    ];

    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly Audit $audit,
        private readonly TokenService $tokens,
        private readonly SessionService $sessions,
        private readonly Crypto $crypto,
    ) {
    }

    public function create(string $caseNumber, string $shopDisplayName, string $contractType = 'standalone'): int
    {
        if (!in_array($contractType, ['salon', 'standalone'], true)) {
            throw new \InvalidArgumentException('contract_type');
        }
        $now = $this->clock->iso();

        return (int)$this->db->transaction(function (\PDO $pdo) use ($caseNumber, $shopDisplayName, $contractType, $now): int {
            $pdo->prepare(
                'INSERT INTO intake_cases
                    (case_number, shop_display_name, contract_type, status, schema_version, created_at, updated_at)
                 VALUES (:case_number, :shop, :contract_type, :status, :schema_version, :created_at, :updated_at)'
            )->execute([
                ':case_number'    => $caseNumber,
                ':shop'           => $shopDisplayName,
                ':contract_type'  => $contractType,
                ':status'         => 'draft',
                ':schema_version' => Migrator::SCHEMA_VERSION,
                ':created_at'     => $now,
                ':updated_at'     => $now,
            ]);
            $caseId = (int)$pdo->lastInsertId();

            // 回答行は空 JSON で初期化する（「行が無い」状態を作らない。SSOT §2.3）
            $cols   = [];
            $params = [':case_id' => $caseId, ':schema_version' => Migrator::SCHEMA_VERSION, ':now' => $now];
            foreach (Migrator::ANSWER_SECTIONS as $section) {
                $cols[':' . $section]                = in_array($section, Migrator::LIST_SECTIONS, true) ? '[]' : '{}';
                $params[':' . $section . '_v']       = $cols[':' . $section];
            }
            $names        = array_map(static fn (string $s): string => $s . '_json', Migrator::ANSWER_SECTIONS);
            $placeholders = array_map(static fn (string $s): string => ':' . $s . '_v', Migrator::ANSWER_SECTIONS);

            $pdo->prepare(
                'INSERT INTO intake_answers (intake_case_id, schema_version, ' . implode(', ', $names) . ', version, created_at, updated_at)
                 VALUES (:case_id, :schema_version, ' . implode(', ', $placeholders) . ', 1, :now, :now)'
            )->execute($params);

            return $caseId;
        });
    }

    /** @return array<string,mixed>|null */
    public function find(int $caseId): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM intake_cases WHERE id = :id');
        $stmt->execute([':id' => $caseId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function transitionTo(int $caseId, string $next): void
    {
        $case = $this->find($caseId);
        if ($case === null) {
            throw new \RuntimeException('case not found');
        }
        $current = (string)$case['status'];
        if (!in_array($next, self::TRANSITIONS[$current] ?? [], true)) {
            throw new \DomainException('transition not allowed');
        }

        $now    = $this->clock->iso();
        $fields = ['status = :status', 'updated_at = :now'];
        $params = [':status' => $next, ':now' => $now, ':id' => $caseId];

        if ($next === 'submitted' && $case['submitted_at'] === null) {
            $fields[]              = 'submitted_at = :submitted_at';
            $params[':submitted_at'] = $now;
        }
        if ($next === 'locked') {
            $fields[]           = 'locked_at = :locked_at';
            $params[':locked_at'] = $now;
        }
        if ($next === 'closed') {
            $fields[]           = 'closed_at = :closed_at';
            $params[':closed_at'] = $now;
        }

        $this->db->pdo()
            ->prepare('UPDATE intake_cases SET ' . implode(', ', $fields) . ' WHERE id = :id')
            ->execute($params);

        // locked / closed では token と session をすべて失効（SSOT §5.2 / §9.2）
        if ($next === 'locked' || $next === 'closed') {
            $this->tokens->revokeAllForCase($caseId, $next);
            $this->sessions->revokeAllForCase($caseId);
        }
    }

    /** Drive フォルダURLは暗号化して保存する（SSOT §7.3）。ログ・応答へ出さない */
    public function setDriveFolder(int $caseId, string $url, string $label): void
    {
        if (strncmp($url, 'https://', 8) !== 0) {
            throw new \InvalidArgumentException('drive url must be https');
        }
        $this->db->pdo()->prepare(
            'UPDATE intake_cases
                SET drive_folder_url_enc = :enc, drive_folder_label = :label, updated_at = :now
              WHERE id = :id'
        )->execute([
            ':enc'   => $this->crypto->encrypt($url),
            ':label' => $label,
            ':now'   => $this->clock->iso(),
            ':id'    => $caseId,
        ]);

        $this->audit->record($caseId, 'drive_url_set', 'ok');
    }

    public function driveFolderUrl(int $caseId): ?string
    {
        $case = $this->find($caseId);
        if ($case === null || $case['drive_folder_url_enc'] === null) {
            return null;
        }

        return $this->crypto->decrypt((string)$case['drive_folder_url_enc']);
    }

    public function confirmDriveUpload(int $caseId): void
    {
        $this->db->pdo()->prepare(
            'UPDATE intake_cases SET drive_upload_confirmed_at = :now, updated_at = :now WHERE id = :id'
        )->execute([':now' => $this->clock->iso(), ':id' => $caseId]);
    }
}
