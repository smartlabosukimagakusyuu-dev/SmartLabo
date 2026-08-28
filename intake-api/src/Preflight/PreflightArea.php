<?php
/**
 * HP Intake API — preflight 専用領域（HP-ONBOARDING-4H-R0 / SSOT v1.12 §9.10）。
 *
 * 本番配置後の通し確認を**正式DBで行わない**ための領域である。
 *
 *   APP_ROOT/preflight/
 *     ├── intake-config.php
 *     ├── intake.sqlite
 *     ├── logs/
 *     ├── ratelimit/
 *     └── backups/
 *
 * ★正式DB・正式バックアップと**完全に分ける**。
 *   混ざると、架空データが本番へ入り、本番データが架空世代へ残る。
 * ★架空データのみ。`example.invalid` のみ。**実メールを送らない**
 *   （通知は NullNotifier）。
 * ★retention_actions_enabled / backup_policy_confirmed は false のまま。
 * ★確認が済んだら領域ごと消す。**そのあとで正式DBを新規作成する。**
 *
 * このクラスは「消してよい場所か」を判定し、消す。
 * ★判定は毎回やり直す。設定を信じない。
 * ★preflight 領域の**外**を1バイトも消さない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Preflight;

use SmartLabo\Intake\Support\PathPolicy;

final class PreflightArea
{
    /** 実削除に必要な確認文字列。★完全一致だけを実行する */
    public const CONFIRM_REMOVE = 'DELETE PREFLIGHT AREA';

    /** preflight 領域の中に置くもの（作成・確認の対象） */
    public const CHILDREN = ['logs', 'ratelimit', 'backups'];

    public function __construct(
        private readonly ?string $configuredRoot,
        /** 正式なデータ置き場。★ここと重なる領域は絶対に消さない */
        private readonly string $productionPrivateRoot,
        /** 稼働DBの実体。★これに触れる操作を通さない */
        private readonly string $productionDbPath,
    ) {
    }

    public function configured(): bool
    {
        return $this->configuredRoot !== null && trim($this->configuredRoot) !== '';
    }

    /**
     * preflight 領域として受け付けてよいかを検査する。
     *
     * 共通のパス規則（絶対・`..` なし・公開領域でない・ホーム直下でない・
     * ルート直下でない・実在・symlink でない・realpath 解決後も同じ）に加えて、
     * **正式領域と重ならないこと**を確かめる。
     *
     * @return array{ok:bool,error?:string,dir?:string}
     */
    public function check(): array
    {
        $checked = PathPolicy::checkDir($this->configuredRoot);
        if ($checked['ok'] !== true) {
            return $checked;
        }
        $dir = (string)$checked['dir'];

        $production = PathPolicy::normalize($this->productionPrivateRoot);
        if (PathPolicy::isSame($dir, $production)) {
            return ['ok' => false, 'error' => 'is_production_root'];
        }
        if (PathPolicy::isInside($production, $dir)) {
            return ['ok' => false, 'error' => 'inside_production_root'];
        }
        if (PathPolicy::isInside($dir, $production)) {
            return ['ok' => false, 'error' => 'contains_production_root'];
        }

        // 稼働DBが中にあるなら、それは preflight 領域ではない
        $db = realpath($this->productionDbPath);
        if ($db !== false) {
            $dbPath = PathPolicy::normalize($db);
            if (PathPolicy::isSame($dbPath, $dir) || PathPolicy::isInside($dir, $dbPath)) {
                return ['ok' => false, 'error' => 'contains_production_db'];
            }
        }

        return ['ok' => true, 'dir' => $dir];
    }

    /**
     * 領域の中身を数える（削除前の事前確認に使う）。
     * ★ファイル名は返すが、中身は読まない。
     *
     * @return array{ok:bool,error?:string,dir?:string,files?:int,dirs?:int,entries?:list<string>}
     */
    public function inventory(): array
    {
        $checked = $this->check();
        if ($checked['ok'] !== true) {
            return $checked;
        }
        $dir = (string)$checked['dir'];

        $files = 0;
        $dirs  = 0;
        $names = [];
        foreach (self::walk($dir) as $path) {
            if (is_dir($path)) {
                ++$dirs;
            } else {
                ++$files;
            }
            // ★相対名だけを返す。絶対パス全文を外へ出さない
            $names[] = ltrim(substr(PathPolicy::normalize($path), strlen($dir)), '/');
        }
        sort($names);

        return ['ok' => true, 'dir' => $dir, 'files' => $files, 'dirs' => $dirs, 'entries' => $names];
    }

    /**
     * preflight 領域を削除する（SSOT v1.12 §9.10-7）。
     *
     * ★**dry-run が既定**。`$apply = true` かつ確認文字列が完全一致のときだけ消す。
     * ★消すのは検査を通った領域の**中だけ**。symlink はたどらない（リンク自体を消す）。
     * ★正式領域・稼働DBに1バイトも触れない（`check()` が二重に確かめている）。
     * ★消したあとに残存0を確かめる。
     *
     * @return array{ok:bool,error?:string,dry_run:bool,removed:int,remaining:int}
     */
    public function remove(bool $apply = false, string $confirm = ''): array
    {
        $checked = $this->check();
        if ($checked['ok'] !== true) {
            return $checked + ['dry_run' => !$apply, 'removed' => 0, 'remaining' => 0];
        }
        $dir = (string)$checked['dir'];

        $targets = iterator_to_array(self::walk($dir), false);

        if (!$apply) {
            return ['ok' => true, 'dry_run' => true, 'removed' => 0, 'remaining' => count($targets), 'dir' => $dir];
        }
        if (!hash_equals(self::CONFIRM_REMOVE, trim($confirm))) {
            return [
                'ok' => false, 'error' => 'confirm_mismatch', 'dry_run' => false,
                'removed' => 0, 'remaining' => count($targets),
            ];
        }

        $removed = 0;
        foreach ($targets as $path) {
            // ★1件ごとに「領域の中か」をもう一度確かめる。一覧を信用しない
            if (!PathPolicy::isInside($dir, PathPolicy::normalize($path))) {
                continue;
            }
            if (is_link($path)) {
                // ★symlink は**たどらず**リンク自体を外す。
                //   POSIX は unlink、Windows のディレクトリ symlink は rmdir で外れる。
                if (!@unlink($path) && !@rmdir($path)) {
                    continue;
                }
                ++$removed;
                continue;
            }
            if (is_dir($path) ? @rmdir($path) : @unlink($path)) {
                ++$removed;
            }
        }
        @rmdir($dir);

        $remaining = is_dir($dir) ? count(iterator_to_array(self::walk($dir), false)) : 0;

        return [
            'ok' => $remaining === 0, 'dry_run' => false,
            'removed' => $removed, 'remaining' => $remaining,
        ] + ($remaining === 0 ? [] : ['error' => 'preflight_entry_remains']);
    }

    /**
     * 領域の中を深いものから順に返す。
     * ★symlink をたどらない（`RecursiveDirectoryIterator` に FOLLOW_SYMLINKS を渡さない）。
     *
     * @return iterable<string>
     */
    private static function walk(string $dir): iterable
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $entry) {
            yield $entry->getPathname();
        }
    }
}
