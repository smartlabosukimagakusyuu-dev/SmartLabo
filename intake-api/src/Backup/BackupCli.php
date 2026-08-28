<?php
/**
 * HP Intake API — バックアップの管理CLI（HP-ONBOARDING-4G / SSOT v1.11 §9.5.7）。
 *
 * Phase 1 の**正式な第一手段はこのCLI**である。管理画面から実行しない。
 *
 * ★Web から実行できる場所へ置かない（`bin/` は public_html の外）。
 * ★秘密値を引数に渡さない。設定は環境変数か `private/intake-config.php` から読む。
 * ★DB の中身を標準出力へ出さない。出すのは
 *   「成功/失敗」「非PIIの件数」「ファイル名」「結果コード」だけである。
 * ★**絶対パス全文を出さない。** 保存先は `<backup_dir>` と表示する。
 * ★削除系は **dry-run が既定**。実削除には `--apply` と確認文字列の完全一致を要求する。
 *
 * 引数の解釈だけをここに置き、実処理は `BackupService` が持つ。
 * こうしておくと、CLI を起動せずに同じ判断をテストできる。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Backup;

final class BackupCli
{
    public const COMMANDS = [
        'backup:create',
        'backup:list',
        'backup:verify',
        'backup:restore-drill',
        'backup:cleanup',
        'backup:purge-preceding-generations',
    ];

    /** @var callable(string):void */
    private $out;

    public function __construct(
        private readonly BackupService $service,
        ?callable $out = null,
    ) {
        $this->out = $out ?? static function (string $line): void {
            echo $line . "\n";
        };
    }

    /**
     * @param list<string> $argv `bin/intake-backup.php` を除いた引数
     * @return int 終了コード（0 = 成功 / 1 = 失敗 / 2 = 使い方の誤り）
     */
    public function run(array $argv): int
    {
        $command = $argv[0] ?? '';
        $options = self::parseOptions(array_slice($argv, 1));

        if ($command === '' || $command === 'help' || $command === '--help') {
            $this->usage();

            return 2;
        }
        // `backup:` を省いた短い名前も受ける（打ち間違いを減らすため）
        if (!in_array($command, self::COMMANDS, true) && in_array('backup:' . $command, self::COMMANDS, true)) {
            $command = 'backup:' . $command;
        }
        if (!in_array($command, self::COMMANDS, true)) {
            $this->line('[NG] 不明なコマンド');
            $this->usage();

            return 2;
        }

        return match ($command) {
            'backup:create'       => $this->create(),
            'backup:list'         => $this->list(),
            'backup:verify'       => $this->verify($options),
            'backup:restore-drill' => $this->drill($options),
            'backup:cleanup'      => $this->cleanup($options),
            default               => $this->purgePreceding($options),
        };
    }

    /* ==================================================== 各コマンド */

    private function create(): int
    {
        $result = $this->service->create();
        if ($result['ok'] !== true) {
            return $this->fail('作成', $result);
        }
        $this->line('[OK] バックアップを作成しました');
        $this->line('  ファイル : ' . $result['name']);
        $this->line('  サイズ   : ' . $result['size'] . ' bytes');
        $this->line('  SHA-256  : ' . $result['sha256']);
        $this->line('  schema   : user_version=' . $result['schema_version']
            . ' / answer=' . $result['answer_schema_version']);

        return 0;
    }

    private function list(): int
    {
        $result = $this->service->listGenerations();
        if ($result['ok'] !== true) {
            return $this->fail('一覧', $result);
        }
        /** @var list<array<string,mixed>> $items */
        $items = $result['items'];

        $this->line('[OK] 世代 ' . count($items) . ' 件（保存先は <backup_dir>）');
        foreach ($items as $item) {
            $this->line(sprintf(
                '  %s  %s  %8d bytes  %s',
                $item['created_at'],
                $item['name'],
                $item['size'],
                $item['has_manifest'] ? 'sha256:' . substr((string)$item['sha256'], 0, 12) . '…' : 'sha256:なし'
            ));
        }
        $this->line('  保持方針 : ' . BackupService::MAX_AGE_DAYS . '日 / 最大 '
            . BackupService::MAX_GENERATIONS . ' 世代');

        return 0;
    }

    /** @param array<string,string> $options */
    private function verify(array $options): int
    {
        $name = $options['name'] ?? '';
        if ($name === '') {
            $this->line('[NG] --name=<ファイル名> が必要です');

            return 2;
        }
        $result = $this->service->verify($name);
        if ($result['ok'] !== true) {
            return $this->fail('検証', $result);
        }
        $this->line('[OK] 検証に成功しました: ' . $name);
        $this->line('  integrity_check   : ' . $result['integrity']);
        $this->line('  foreign_key_check : ' . $result['foreign_key_violations'] . ' 件');
        $this->line('  user_version      : ' . $result['schema_version']);
        $this->line('  回答schema        : ' . $result['answer_schema_version']);
        $this->line('  表                : ' . $result['tables'] . ' 個');

        return 0;
    }

    /** @param array<string,string> $options */
    private function drill(array $options): int
    {
        $name = $options['name'] ?? '';
        if ($name === '') {
            $this->line('[NG] --name=<ファイル名> が必要です');

            return 2;
        }
        $result = $this->service->restoreDrill($name);
        if ($result['ok'] !== true) {
            return $this->fail('復元確認', $result);
        }
        $this->line('[OK] 復元確認に成功しました: ' . $name);
        $this->line('  一時DBは削除済み  : ' . ($result['temp_removed'] ? 'はい' : 'いいえ'));
        $this->line('  稼働DBは無変更    : ' . ($result['source_unchanged'] ? 'はい' : 'いいえ'));
        $this->line('  案件行            : ' . $result['cases'] . ' 件（内容は表示しない）');
        $this->line('  件数一致          : ' . ($result['counts_match'] ? 'はい' : 'いいえ（時点差・異常ではない）'));
        $this->line('  ★稼働DBへ書き戻していません。本番復元は 4H 以降の別承認工程です。');

        return 0;
    }

    /** @param array<string,string> $options */
    private function cleanup(array $options): int
    {
        $apply   = array_key_exists('apply', $options);
        $confirm = $options['confirm'] ?? '';

        $result = $this->service->cleanup($apply, $confirm);
        if ($result['ok'] !== true) {
            return $this->fail('cleanup', $result);
        }

        $this->line($result['dry_run'] ? '[OK] cleanup（dry-run。1件も削除していません）' : '[OK] cleanup を実行しました');
        $this->line('  30日超   : ' . count($result['expired']) . ' 件');
        $this->line('  世代超過 : ' . count($result['excess']) . ' 件');
        foreach (array_merge($result['expired'], $result['excess']) as $name) {
            $this->line('    - ' . $name);
        }
        $this->line('  削除実行 : ' . $result['deleted'] . ' 件');
        if ($result['dry_run']) {
            $this->line('  実削除するには: --apply --confirm="' . BackupService::CONFIRM_CLEANUP . '"');
            $this->line('  ★削除した世代からは二度と復元できません。');
        }

        return 0;
    }

    /** @param array<string,string> $options */
    private function purgePreceding(array $options): int
    {
        $apply   = array_key_exists('apply', $options);
        $confirm = $options['confirm'] ?? '';

        $result = $this->service->purgePrecedingGenerations($apply, $confirm);
        if ($result['ok'] !== true) {
            $this->fail('purge前世代の削除', $result);
            $this->line('  ★古い世代は**1件も削除していません**。');
            $this->line('  ★保持削除は「バックアップ側未完了」です。runbook の再実行手順に従ってください。');

            return 1;
        }

        $this->line($result['dry_run']
            ? '[OK] purge前世代の削除（dry-run。1件も削除していません）'
            : '[OK] purge前世代を削除しました');
        $this->line('  保持削除の実行時刻 : ' . $result['purged_at']);
        $this->line('  検証した世代       : ' . ($result['checked'] ?? '-'));
        $this->line('  purge前の世代      : ' . count($result['preceding']) . ' 件');
        foreach ($result['preceding'] as $name) {
            $this->line('    - ' . $name);
        }
        $this->line('  削除実行           : ' . $result['deleted'] . ' 件');
        $this->line('  残ったpurge前世代  : ' . $result['remaining_preceding'] . ' 件');
        if ($result['dry_run']) {
            $this->line('  実削除するには: --apply --confirm="' . BackupService::CONFIRM_PURGE_PRECEDING . '"');
            $this->line('  ★削除した世代からは二度と復元できません。');
        }

        return 0;
    }

    /* ==================================================== 補助 */

    /** @param array<string,mixed> $result */
    private function fail(string $label, array $result): int
    {
        $this->line('[NG] ' . $label . 'に失敗しました（' . (string)($result['error'] ?? 'unknown') . '）');
        if (($result['error'] ?? '') === 'not_configured') {
            $this->line('  backup_dir が未設定です。private/intake-config.php か INTAKE_BACKUP_DIR で指定してください。');
        }
        if (($result['error'] ?? '') === 'lock_busy') {
            $this->line('  他のバックアップ操作が実行中です。終わってからやり直してください。');
        }

        return 1;
    }

    private function usage(): void
    {
        $this->line('HP Intake — バックアップ管理CLI（ローカル/サーバーの端末からのみ）');
        $this->line('');
        $this->line('  php bin/intake-backup.php <command> [options]');
        $this->line('');
        foreach (self::COMMANDS as $c) {
            $this->line('    ' . $c);
        }
        $this->line('');
        $this->line('  --name=<ファイル名>  verify / restore-drill の対象');
        $this->line('  --apply              削除を実際に行う（既定は dry-run）');
        $this->line('  --confirm="…"        実削除に必要な確認文字列');
    }

    private function line(string $text): void
    {
        ($this->out)($text);
    }

    /**
     * `--key=value` と `--flag` だけを解釈する。
     * ★位置引数を受けない。パスを引数で受け取らないため（設定からのみ読む）。
     *
     * @param list<string> $args
     * @return array<string,string>
     */
    public static function parseOptions(array $args): array
    {
        $out = [];
        foreach ($args as $arg) {
            if (strncmp($arg, '--', 2) !== 0) {
                continue;
            }
            $body = substr($arg, 2);
            $eq   = strpos($body, '=');
            if ($eq === false) {
                $out[$body] = '';
                continue;
            }
            $out[substr($body, 0, $eq)] = substr($body, $eq + 1);
        }

        return $out;
    }
}
