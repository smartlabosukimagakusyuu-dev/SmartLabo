<?php
/**
 * HP Intake API — バックアップの世代管理・検証・復元確認（HP-ONBOARDING-4G / SSOT v1.11 §9.5）。
 *
 * 4G の責任範囲:
 *   - `SQLite3::backup()` で1世代を安全に作る（一時ファイル → 検証 → atomic rename）
 *   - 作ったものが**本当に復元できる**ことを、使い捨ての一時DBで証明する（restore drill）
 *   - 30日・60世代の保持と、その範囲を超えたものの手動 cleanup
 *   - 保持削除（retention purge）の**前に作られた世代**を、後から消し残さない
 *
 * ★本番DBへ書き戻す restore は**作らない**。4G が持つのは drill だけである（§9.5.5）。
 * ★自動実行しない。cron を作らない。ページ表示から呼ばない。
 *   入り口は管理CLI（`bin/intake-backup.php`）だけである。
 * ★DB のトランザクションと filesystem の削除は**ひとつの原子操作にならない**。
 *   段階・状態確認・冪等な再実行・失敗時 runbook で安全性を担保する（§9.5.6）。
 * ★応答・ログ・監査へ回答本文・店舗名・案件番号・絶対パス全文を出さない。
 *   出してよいのは「成功/失敗」「非PIIの件数」「ファイル名」だけである。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Backup;

use SmartLabo\Intake\Db;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Service\Audit;
use SmartLabo\Intake\Support\Clock;

final class BackupService
{
    /** 保持日数（SSOT v1.11 §9.5.4-1） */
    public const MAX_AGE_DAYS = 30;

    /** 世代数の上限（SSOT v1.11 §9.5.4-3） */
    public const MAX_GENERATIONS = 60;

    /** 実削除に必要な確認文字列。★完全一致だけを実行する */
    public const CONFIRM_CLEANUP           = 'DELETE OLD BACKUPS';
    public const CONFIRM_PURGE_PRECEDING   = 'DELETE PRE-PURGE BACKUPS';

    /** 復元確認で存在していなければならない8表（SSOT §2.1〜§2.8） */
    public const REQUIRED_TABLES = [
        'intake_cases', 'intake_tokens', 'intake_answers', 'intake_submission_history',
        'intake_audit_events', 'intake_sessions', 'intake_admin_sessions', 'intake_revision_requests',
    ];

    /** 復元確認で案件行に許す状態（CaseService::STATUSES と同じ語彙） */
    private const CASE_STATUSES = ['draft', 'submitted', 'needs_revision', 'reviewed', 'locked', 'closed'];

    /**
     * 件数の比較に使う SQL。
     * ★表名を文字列連結で組み立てない。**1文ずつリテラルで書く**（RetentionService と同じ書き方）。
     *   どの表を数えるかがそのまま「何を比べているか」なので、grep できる形に置く。
     */
    private const COUNT_SQL = [
        'intake_cases'              => 'SELECT COUNT(*) FROM intake_cases',
        'intake_tokens'             => 'SELECT COUNT(*) FROM intake_tokens',
        'intake_answers'            => 'SELECT COUNT(*) FROM intake_answers',
        'intake_submission_history' => 'SELECT COUNT(*) FROM intake_submission_history',
        'intake_audit_events'       => 'SELECT COUNT(*) FROM intake_audit_events',
        'intake_sessions'           => 'SELECT COUNT(*) FROM intake_sessions',
        'intake_admin_sessions'     => 'SELECT COUNT(*) FROM intake_admin_sessions',
        'intake_revision_requests'  => 'SELECT COUNT(*) FROM intake_revision_requests',
    ];

    /** 回答スキーマ版を持つ2表。★同じくリテラルのみ */
    private const ANSWER_VERSION_SQL = [
        'SELECT MAX(schema_version) FROM intake_cases',
        'SELECT MAX(schema_version) FROM intake_answers',
    ];

    private readonly SqliteBackup $sqlite;

    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly Audit $audit,
        private readonly ?string $configuredDir,
    ) {
        $this->sqlite = new SqliteBackup($this->db);
    }

    /* ==================================================== 置き場所 */

    /**
     * 設定された保存先を**使う直前に**検査する。
     * @return array{ok:bool,error?:string,dir?:string}
     */
    public function dir(): array
    {
        return BackupPaths::checkDir($this->configuredDir);
    }

    /** 設定されているか（画面・CLI の入口判定用。ここでは実体を見ない） */
    public function configured(): bool
    {
        return $this->configuredDir !== null && trim($this->configuredDir) !== '';
    }

    /* ==================================================== 一覧 */

    /**
     * 世代の一覧（古い順）。
     * ★規則どおりの名前・実ファイル・symlink でないものだけを世代として数える。
     *
     * @return array{ok:bool,error?:string,items?:list<array<string,mixed>>}
     */
    public function listGenerations(): array
    {
        $dir = $this->dir();
        if ($dir['ok'] !== true) {
            return $dir;
        }

        return ['ok' => true, 'items' => $this->listIn((string)$dir['dir'])];
    }

    /** @return list<array<string,mixed>> */
    private function listIn(string $dir): array
    {
        $manifest = (new BackupManifest($dir))->entries();
        $items    = [];

        foreach (scandir($dir) ?: [] as $name) {
            if (preg_match(BackupPaths::FILE_PATTERN, $name) !== 1) {
                continue;
            }
            $path = $dir . '/' . $name;
            if (!is_file($path) || is_link($path)) {
                continue;
            }
            $ts = BackupPaths::timestampOf($name);
            if ($ts === null) {
                continue;
            }
            $items[] = [
                'name'         => $name,
                'created_at'   => gmdate('Y-m-d\TH:i:s\Z', $ts),
                'created_ts'   => $ts,
                'size'         => (int)filesize($path),
                'sha256'       => (string)($manifest[$name]['sha256'] ?? ''),
                'has_manifest' => isset($manifest[$name]),
            ];
        }

        usort($items, static fn (array $a, array $b): int
            => [$a['created_ts'], $a['name']] <=> [$b['created_ts'], $b['name']]);

        return $items;
    }

    /* ==================================================== 作成 */

    /**
     * 世代を1つ作る（SSOT v1.11 §9.5.3）。
     *
     * 順序を崩さない:
     *   排他ロック → 一時ファイルへ取得 → integrity/foreign_key → SHA-256 →
     *   ディスクへ同期 → 権限600 → **同一ディレクトリ内で atomic rename** → 一覧へ記録
     *
     * ★既存のバックアップを上書きしない。同名になった場合は作らずに失敗させる。
     * ★失敗したら一時ファイルを必ず消す。中途半端なファイルを世代として残さない。
     *
     * @return array{ok:bool,error?:string,name?:string,sha256?:string,size?:int}
     */
    public function create(): array
    {
        $dir = $this->dir();
        if ($dir['ok'] !== true) {
            return $dir;
        }
        $canonical = (string)$dir['dir'];

        return $this->withLock($canonical, function () use ($canonical): array {
            $result = $this->createIn($canonical);
            $this->audit->record(null, 'backup_created', $result['ok'] === true ? 'ok' : (string)$result['error']);

            return $result;
        });
    }

    /** @return array{ok:bool,error?:string,name?:string,sha256?:string,size?:int} */
    private function createIn(string $dir): array
    {
        $name = null;
        for ($i = 0; $i < 8; ++$i) {
            $candidate = BackupPaths::makeName($this->clock->now());
            if (!file_exists($dir . '/' . $candidate)) {
                $name = $candidate;
                break;
            }
        }
        if ($name === null) {
            return ['ok' => false, 'error' => 'name_collision'];
        }

        $tmp = $dir . '/' . BackupPaths::TEMP_PREFIX . bin2hex(random_bytes(8)) . BackupPaths::TEMP_EXT;
        if (file_exists($tmp)) {
            return ['ok' => false, 'error' => 'temp_collision'];
        }

        try {
            // ★SQLite3::backup()。単純コピーではない（SSOT §9.6.1）
            $taken = $this->sqlite->backupTo($tmp);
        } catch (\Throwable $e) {
            @unlink($tmp);

            return ['ok' => false, 'error' => 'backup_failed'];
        }
        if (($taken['integrity'] ?? '') !== 'ok' || ($taken['foreign_key_violations'] ?? 1) !== 0) {
            @unlink($tmp);

            return ['ok' => false, 'error' => 'integrity_failed'];
        }

        $sha  = hash_file('sha256', $tmp);
        $size = filesize($tmp);
        if ($sha === false || $size === false) {
            @unlink($tmp);

            return ['ok' => false, 'error' => 'hash_failed'];
        }
        $versions = self::readVersions($tmp);

        SqliteBackup::flushToDisk($tmp);
        @chmod($tmp, 0600);

        $final = $dir . '/' . $name;
        if (file_exists($final)) {
            // ★上書きしない。世代を1つ失うより、作らない方が安全である
            @unlink($tmp);

            return ['ok' => false, 'error' => 'name_collision'];
        }
        if (!@rename($tmp, $final)) {
            @unlink($tmp);

            return ['ok' => false, 'error' => 'rename_failed'];
        }
        @chmod($final, 0600);

        (new BackupManifest($dir))->put($name, [
            'created_at'            => gmdate('Y-m-d\TH:i:s\Z', (int)BackupPaths::timestampOf($name)),
            'size'                  => (int)$size,
            'sha256'                => (string)$sha,
            'schema_version'        => $versions['schema_version'],
            'answer_schema_version' => $versions['answer_schema_version'],
        ]);

        return [
            'ok'     => true,
            'name'   => $name,
            'sha256' => (string)$sha,
            'size'   => (int)$size,
        ] + $versions;
    }

    /* ==================================================== 検証 */

    /**
     * 世代1つを検証する（SSOT v1.11 §9.5.3-8）。
     *
     * SHA-256 の控えと突き合わせ、SQLite として開き、
     * integrity_check / foreign_key_check / user_version / 8表 / 回答スキーマ版を見る。
     * ★一覧に控えが無いファイルは「検証できない」として**通さない**（安全側）。
     *
     * @return array{ok:bool,error?:string}
     */
    public function verify(string $name): array
    {
        $dir = $this->dir();
        if ($dir['ok'] !== true) {
            return $dir;
        }

        return $this->verifyIn((string)$dir['dir'], $name);
    }

    /** @return array{ok:bool,error?:string} */
    private function verifyIn(string $dir, string $name): array
    {
        $file = BackupPaths::checkFile($dir, $name);
        if ($file['ok'] !== true) {
            return $file;
        }
        $path = (string)$file['path'];

        $entry = (new BackupManifest($dir))->entry($name);
        if ($entry === null || !is_string($entry['sha256'] ?? null) || $entry['sha256'] === '') {
            return ['ok' => false, 'error' => 'no_manifest_entry'];
        }

        $sha = hash_file('sha256', $path);
        if ($sha === false || !hash_equals((string)$entry['sha256'], $sha)) {
            return ['ok' => false, 'error' => 'sha_mismatch'];
        }
        if (isset($entry['size']) && (int)$entry['size'] !== (int)filesize($path)) {
            return ['ok' => false, 'error' => 'size_mismatch'];
        }

        // ★`+` は左辺を優先する。`ok` を左に置くと検査結果を握りつぶすため、
        //   先に判定してから合成する。
        $inspected = self::inspect($path);
        if ($inspected['ok'] !== true) {
            return $inspected;
        }

        return ['name' => $name, 'sha256' => $sha] + $inspected;
    }

    /**
     * SQLite ファイルの中身を検査する（開く → 5項目）。
     * ★元DBには一切触れない。渡されたファイルだけを見る。
     *
     * @return array{ok:bool,error?:string,...}
     */
    public static function inspect(string $path): array
    {
        try {
            $pdo = new \PDO('sqlite:' . $path, null, null, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $integrity = (string)$pdo->query('PRAGMA integrity_check')->fetchColumn();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'not_a_database'];
        }

        if ($integrity !== 'ok') {
            return ['ok' => false, 'error' => 'integrity_failed', 'integrity' => $integrity];
        }
        $fk = count($pdo->query('PRAGMA foreign_key_check')->fetchAll());
        if ($fk !== 0) {
            return ['ok' => false, 'error' => 'foreign_key_violation', 'foreign_key_violations' => $fk];
        }

        $userVersion = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
        if ($userVersion !== Migrator::SCHEMA_VERSION) {
            return ['ok' => false, 'error' => 'schema_version_mismatch', 'schema_version' => $userVersion];
        }

        $tables  = array_column($pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name"
        )->fetchAll(), 'name');
        $missing = array_values(array_diff(self::REQUIRED_TABLES, $tables));
        if ($missing !== []) {
            return ['ok' => false, 'error' => 'missing_tables', 'missing' => $missing];
        }

        $answerVersion = self::maxAnswerSchema($pdo);
        if ($answerVersion > Migrator::ANSWER_SCHEMA_VERSION) {
            return ['ok' => false, 'error' => 'answer_schema_mismatch', 'answer_schema_version' => $answerVersion];
        }

        return [
            'ok'                     => true,
            'integrity'              => $integrity,
            'foreign_key_violations' => 0,
            'schema_version'         => $userVersion,
            'answer_schema_version'  => $answerVersion,
            'tables'                 => count($tables),
        ];
    }

    /* ==================================================== 復元確認（drill） */

    /**
     * 復元できることを実際に確かめる（SSOT v1.11 §9.5.5）。
     *
     * ★**稼働DBへ書き戻さない。** 使い捨ての一時DBへ復元し、確かめ、消すだけである。
     * ★本番復元は 4H 以降の別承認工程。ここには経路を作らない。
     *
     * @return array{ok:bool,error?:string}
     */
    public function restoreDrill(string $name): array
    {
        $dir = $this->dir();
        if ($dir['ok'] !== true) {
            return $dir;
        }
        $canonical = (string)$dir['dir'];

        return $this->withLock($canonical, function () use ($canonical, $name): array {
            $result = $this->drillIn($canonical, $name);
            $this->audit->record(null, 'backup_restore_drill', $result['ok'] === true ? 'ok' : (string)$result['error']);

            return $result;
        });
    }

    /** @return array{ok:bool,error?:string} */
    private function drillIn(string $dir, string $name): array
    {
        $verified = $this->verifyIn($dir, $name);
        if ($verified['ok'] !== true) {
            return $verified + ['stage' => 'verify'];
        }
        $source = $dir . '/' . $name;

        $livePath   = $this->db->path();
        $liveBefore = $livePath === ':memory:' ? '' : (string)hash_file('sha256', $livePath);

        $drillDir = $dir . '/' . BackupPaths::DRILL_PREFIX . bin2hex(random_bytes(6));
        if (file_exists($drillDir) || !@mkdir($drillDir, 0700, true)) {
            return ['ok' => false, 'error' => 'drill_dir_failed'];
        }
        $restored = $drillDir . '/restore.sqlite';

        try {
            // ★稼働DBへ書かないことを、パス比較でも二重に確かめる
            if ($livePath !== ':memory:'
                && BackupPaths::normalize((string)realpath(dirname($restored)) . '/restore.sqlite')
                   === BackupPaths::normalize((string)realpath($livePath))) {
                return ['ok' => false, 'error' => 'refuses_to_overwrite_live_db'];
            }
            if (!@copy($source, $restored)) {
                return ['ok' => false, 'error' => 'copy_failed'];
            }
            @chmod($restored, 0600);

            $inspected = self::inspect($restored);
            if ($inspected['ok'] !== true) {
                return $inspected + ['stage' => 'restored'];
            }

            $cases = self::caseConsistency($restored);
            if ($cases['ok'] !== true) {
                return $cases + ['stage' => 'cases'];
            }

            $compare = $this->compareWithLive($restored);
        } finally {
            // ★成否にかかわらず一時DBを残さない
            @unlink($restored);
            @rmdir($drillDir);
        }

        $liveAfter = $livePath === ':memory:' ? '' : (string)hash_file('sha256', $livePath);

        return [
            'name'             => $name,
            'temp_removed'     => !is_dir($drillDir) && !is_file($restored),
            'source_unchanged' => hash_equals($liveBefore, $liveAfter),
            'cases'            => $cases['cases'],
        ] + $inspected + $compare;
    }

    /**
     * 復元したDBの案件行が壊れていないか（非PIIの形式検査だけ）。
     * ★案件番号・店舗名を返さない。返すのは件数と真偽だけである。
     *
     * @return array{ok:bool,error?:string,cases:int}
     */
    private static function caseConsistency(string $path): array
    {
        $pdo  = new \PDO('sqlite:' . $path, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rows = $pdo->query('SELECT case_number, status, created_at, schema_version FROM intake_cases')->fetchAll();

        foreach ($rows as $row) {
            if (trim((string)$row['case_number']) === '') {
                return ['ok' => false, 'error' => 'case_number_missing', 'cases' => count($rows)];
            }
            if (!in_array((string)$row['status'], self::CASE_STATUSES, true)) {
                return ['ok' => false, 'error' => 'case_status_unknown', 'cases' => count($rows)];
            }
            if (trim((string)$row['created_at']) === '') {
                return ['ok' => false, 'error' => 'case_created_at_missing', 'cases' => count($rows)];
            }
            if ((int)$row['schema_version'] > Migrator::ANSWER_SCHEMA_VERSION) {
                return ['ok' => false, 'error' => 'answer_schema_mismatch', 'cases' => count($rows)];
            }
        }

        return ['ok' => true, 'cases' => count($rows)];
    }

    /**
     * 元DBと復元DBの**非PII指標**だけを比べる。
     *
     * ★件数が違っても失敗にしない。バックアップは「ある時点」の写しであり、
     *   その後の入力で件数が増えているのは正常である。**事実として報告するだけ**にする。
     *
     * @return array{counts:array<string,array{live:int,restored:int}>,counts_match:bool}
     */
    private function compareWithLive(string $restoredPath): array
    {
        $restored = new \PDO('sqlite:' . $restoredPath, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $counts = [];
        $match  = true;
        foreach (self::COUNT_SQL as $table => $sql) {
            // ★SQL はクラス定数のリテラルだけ。組み立て途中の文字列を渡さない
            $live = (int)self::scalar($this->db->pdo(), $sql);
            $back = (int)self::scalar($restored, $sql);
            $counts[$table] = ['live' => $live, 'restored' => $back];
            if ($live !== $back) {
                $match = false;
            }
        }

        return ['counts' => $counts, 'counts_match' => $match];
    }

    /* ==================================================== cleanup */

    /**
     * 30日超・60世代超を消す（SSOT v1.11 §9.5.4）。
     *
     * 順序:
     *   1. 30日を過ぎたものを削除対象にする
     *   2. 残りが60世代を超えていれば、古いものから超過分を削除対象にする
     *   3. 稼働DBは対象にしない
     *   4. バックアップディレクトリの外は絶対に対象にしない
     *
     * ★**既定は dry-run**。`$apply = true` かつ確認文字列が完全一致のときだけ実際に消す。
     *
     * @return array{ok:bool,error?:string,dry_run:bool,expired:list<string>,excess:list<string>,deleted:int}
     */
    public function cleanup(bool $apply = false, string $confirm = ''): array
    {
        $dir = $this->dir();
        if ($dir['ok'] !== true) {
            return $dir + ['dry_run' => !$apply, 'expired' => [], 'excess' => [], 'deleted' => 0];
        }
        $canonical = (string)$dir['dir'];

        return $this->withLock($canonical, function () use ($canonical, $apply, $confirm): array {
            $items  = $this->listIn($canonical);
            $cutoff = $this->clock->now() - (self::MAX_AGE_DAYS * 86400);

            $expired = [];
            $kept    = [];
            foreach ($items as $item) {
                if ((int)$item['created_ts'] < $cutoff) {
                    $expired[] = (string)$item['name'];
                    continue;
                }
                $kept[] = $item;
            }

            $excess = [];
            if (count($kept) > self::MAX_GENERATIONS) {
                foreach (array_slice($kept, 0, count($kept) - self::MAX_GENERATIONS) as $item) {
                    $excess[] = (string)$item['name'];
                }
            }

            $targets = array_values(array_unique(array_merge($expired, $excess)));
            $base    = [
                'ok' => true, 'dry_run' => !$apply,
                'expired' => $expired, 'excess' => $excess, 'targets' => count($targets),
            ];

            if (!$apply) {
                // ★既定はここで終わる。**1件も消さない**
                return $base + ['deleted' => 0];
            }
            if (!hash_equals(self::CONFIRM_CLEANUP, trim($confirm))) {
                return ['ok' => false, 'error' => 'confirm_mismatch'] + $base + ['deleted' => 0];
            }

            $deleted = $this->deleteAll($canonical, $targets);
            $this->audit->record(null, 'backup_cleanup', 'ok');

            return $base + ['dry_run' => false, 'deleted' => $deleted, 'remaining' => count($this->listIn($canonical))];
        });
    }

    /* ==================================================== 保持削除との連動 */

    /**
     * 保持削除（retention purge）より前に作られた世代を消す（SSOT v1.11 §9.5.6）。
     *
     * 案件を稼働DBから物理削除しても、**削除前のバックアップが残っている間は
     * そこから PII を復元できてしまう**。通常の30日保持より優先して消す必要がある。
     *
     * 段階（途中で止めても壊れない・何度でもやり直せる）:
     *   1. 監査から最後の `retention_purged` の時刻を読む
     *   2. その後に作られた世代（purge後バックアップ）があるか
     *   3. purge後バックアップを verify し、restore drill を成功させる
     *   4. **ここまで成功したときにだけ**、purge前の世代を消す
     *   5. 再走査して purge前世代が0件であることを確かめる
     *
     * ★3 が失敗したら**古い世代を1件も消さない**。
     *   「消したいものが復元できる状態」より「戻せる世代がない状態」の方が危険である。
     *
     * @return array{ok:bool,error?:string,dry_run:bool,preceding:list<string>,deleted:int,remaining_preceding:int}
     */
    public function purgePrecedingGenerations(bool $apply = false, string $confirm = ''): array
    {
        $empty = ['dry_run' => !$apply, 'preceding' => [], 'deleted' => 0, 'remaining_preceding' => 0];

        $dir = $this->dir();
        if ($dir['ok'] !== true) {
            return $dir + $empty;
        }
        $canonical = (string)$dir['dir'];

        return $this->withLock($canonical, function () use ($canonical, $apply, $confirm, $empty): array {
            $cutoffIso = $this->lastRetentionPurgeAt();
            if ($cutoffIso === null) {
                return ['ok' => false, 'error' => 'no_purge_recorded'] + $empty;
            }
            $cutoffTs = strtotime($cutoffIso);
            if ($cutoffTs === false) {
                return ['ok' => false, 'error' => 'no_purge_recorded'] + $empty;
            }

            $items     = $this->listIn($canonical);
            $preceding = [];
            $after     = [];
            foreach ($items as $item) {
                // ★同じ秒に作られた世代は「purge前」に倒す。安全側の丸めである
                if ((int)$item['created_ts'] <= $cutoffTs) {
                    $preceding[] = (string)$item['name'];
                    continue;
                }
                $after[] = $item;
            }

            $base = [
                'ok' => true, 'dry_run' => !$apply, 'purged_at' => $cutoffIso,
                'preceding' => $preceding, 'deleted' => 0,
                'remaining_preceding' => count($preceding),
            ];

            if ($after === []) {
                return ['ok' => false, 'error' => 'post_purge_backup_missing'] + $base;
            }

            // 3. 最新の purge後バックアップが**本当に復元できる**ことを先に証明する
            $newest = (string)$after[count($after) - 1]['name'];
            $drill  = $this->drillIn($canonical, $newest);
            if ($drill['ok'] !== true) {
                $this->audit->record(null, 'backup_restore_drill', (string)$drill['error']);

                return ['ok' => false, 'error' => 'post_purge_backup_unverified', 'checked' => $newest] + $base;
            }
            $this->audit->record(null, 'backup_restore_drill', 'ok');

            if (!$apply) {
                return $base + ['checked' => $newest];
            }
            if (!hash_equals(self::CONFIRM_PURGE_PRECEDING, trim($confirm))) {
                return ['ok' => false, 'error' => 'confirm_mismatch'] + $base;
            }

            $deleted   = $this->deleteAll($canonical, $preceding);
            $remaining = 0;
            foreach ($this->listIn($canonical) as $item) {
                if ((int)$item['created_ts'] <= $cutoffTs) {
                    ++$remaining;
                }
            }
            $this->audit->record(null, 'backup_generations_purged', $remaining === 0 ? 'ok' : 'incomplete');

            return [
                'ok' => $remaining === 0, 'dry_run' => false, 'purged_at' => $cutoffIso,
                'preceding' => $preceding, 'checked' => $newest,
                'deleted' => $deleted, 'remaining_preceding' => $remaining,
            ] + ($remaining === 0 ? [] : ['error' => 'preceding_generation_remains']);
        });
    }

    /** 最後に保持削除を実行した時刻（監査から読む。案件は特定しない） */
    public function lastRetentionPurgeAt(): ?string
    {
        $stmt = $this->db->pdo()->query(
            "SELECT MAX(created_at) FROM intake_audit_events WHERE event_type = 'retention_purged'"
        );
        $value = $stmt->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    /* ==================================================== 補助 */

    /**
     * 名前の一覧を消す。
     * ★1件ごとに**もう一度**「許可ディレクトリ内の正式な名前か」を検査する。
     *   呼び出し側で組み立てた一覧を信用しない。
     *
     * @param list<string> $names
     */
    private function deleteAll(string $dir, array $names): int
    {
        $manifest = new BackupManifest($dir);
        $live     = $this->db->path() === ':memory:' ? null : realpath($this->db->path());
        $deleted  = 0;

        foreach ($names as $name) {
            $file = BackupPaths::checkFile($dir, $name);
            if ($file['ok'] !== true) {
                continue;
            }
            $path = (string)$file['path'];
            // ★稼働DBを絶対に消さない（名前規則で届かないはずだが、実体でも確かめる）
            if ($live !== false && $live !== null
                && BackupPaths::normalize($path) === BackupPaths::normalize($live)) {
                continue;
            }
            if (@unlink($path)) {
                ++$deleted;
                $manifest->forget($name);
            }
        }
        $manifest->pruneMissing(array_column($this->listIn($dir), 'name'));

        return $deleted;
    }

    /**
     * バックアップ操作の排他ロック（SSOT v1.11 §9.5.3-10）。
     *
     * ★作成・cleanup・drill・purge連動を**同時に走らせない**。
     *   取れなければ待たずに固定の応答で中止する（`lock_busy`）。
     *
     * @param callable():array<string,mixed> $fn
     * @return array<string,mixed>
     */
    private function withLock(string $dir, callable $fn): array
    {
        $lockPath = $dir . '/' . BackupPaths::LOCK_NAME;
        $handle   = @fopen($lockPath, 'c');
        if ($handle === false) {
            return ['ok' => false, 'error' => 'lock_unavailable'];
        }
        @chmod($lockPath, 0600);

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return ['ok' => false, 'error' => 'lock_busy'];
        }

        try {
            return $fn();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array{schema_version:int,answer_schema_version:int} */
    private static function readVersions(string $path): array
    {
        try {
            $pdo = new \PDO('sqlite:' . $path, null, null, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            return [
                'schema_version'        => (int)$pdo->query('PRAGMA user_version')->fetchColumn(),
                'answer_schema_version' => self::maxAnswerSchema($pdo),
            ];
        } catch (\Throwable $e) {
            return ['schema_version' => 0, 'answer_schema_version' => 0];
        }
    }

    /** 回答スキーマ版（行が無ければ現行の版とみなす） */
    private static function maxAnswerSchema(\PDO $pdo): int
    {
        $max = 0;
        foreach (self::ANSWER_VERSION_SQL as $sql) {
            $value = self::scalar($pdo, $sql);
            if ($value !== null && $value !== false) {
                $max = max($max, (int)$value);
            }
        }

        return $max === 0 ? Migrator::ANSWER_SCHEMA_VERSION : $max;
    }

    /**
     * クラス定数のリテラル SQL を1つ実行して先頭列を返す。
     * ★引数に受け取ってよいのは**このクラスの定数だけ**。外部入力を渡さない。
     */
    private static function scalar(\PDO $pdo, string $literalSql): mixed
    {
        $stmt = $pdo->prepare($literalSql);
        $stmt->execute();

        return $stmt->fetchColumn();
    }
}
