<?php
/**
 * HP Intake API — バックアップの一覧ファイル（SSOT v1.11 §9.5.2）。
 *
 * ★ここに入れてよいのは**非PII の事実だけ**である。
 *   ファイル名 / 作成日時 / サイズ / SHA-256 / DBスキーマ版 / 回答スキーマ版。
 *   案件番号・店舗名・回答件数・メール・Drive URL を**書かない**。
 * ★バックアップのメタデータを DB の中へ保存しない（DB ごと失った時に読めないため）。
 * ★この一覧は「改ざん検出のための SHA-256 の控え」である。
 *   一覧に無いファイルは**検証できない**として扱う（安全側へ倒す）。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Backup;

final class BackupManifest
{
    public const VERSION = 1;

    /** 一覧に置いてよいキー。★これ以外は読み書きしない */
    public const ENTRY_KEYS = ['created_at', 'size', 'sha256', 'schema_version', 'answer_schema_version'];

    public function __construct(private readonly string $canonicalDir)
    {
    }

    public function path(): string
    {
        return $this->canonicalDir . '/' . BackupPaths::MANIFEST_NAME;
    }

    /** @return array<string,array<string,mixed>> ファイル名 => 事実 */
    public function entries(): array
    {
        $path = $this->path();
        if (!is_file($path) || is_link($path)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded) || !is_array($decoded['entries'] ?? null)) {
            return [];
        }

        $out = [];
        foreach ($decoded['entries'] as $name => $entry) {
            if (!is_string($name) || preg_match(BackupPaths::FILE_PATTERN, $name) !== 1 || !is_array($entry)) {
                continue;
            }
            $out[$name] = self::filter($entry);
        }
        ksort($out);

        return $out;
    }

    /** @return array<string,mixed>|null */
    public function entry(string $name): ?array
    {
        return $this->entries()[$name] ?? null;
    }

    /** @param array<string,mixed> $entry */
    public function put(string $name, array $entry): void
    {
        $all        = $this->entries();
        $all[$name] = self::filter($entry);
        $this->write($all);
    }

    public function forget(string $name): void
    {
        $all = $this->entries();
        unset($all[$name]);
        $this->write($all);
    }

    /**
     * 実ファイルと一覧を突き合わせ、消えたファイルの行を落とす。
     * @param list<string> $existingNames
     */
    public function pruneMissing(array $existingNames): void
    {
        $all  = $this->entries();
        $keep = array_intersect_key($all, array_flip($existingNames));
        if (count($keep) !== count($all)) {
            $this->write($keep);
        }
    }

    /** @param array<string,array<string,mixed>> $entries */
    private function write(array $entries): void
    {
        ksort($entries);
        $json = json_encode(
            ['version' => self::VERSION, 'entries' => $entries],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        // ★書き換え中の一覧を残さない。同一ディレクトリ内で作ってから rename する
        $tmp = $this->canonicalDir . '/' . BackupPaths::TEMP_PREFIX . bin2hex(random_bytes(6)) . BackupPaths::TEMP_EXT;
        if (file_put_contents($tmp, (string)$json) === false) {
            @unlink($tmp);

            return;
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $this->path())) {
            // Windows は既存ファイルがあると rename が失敗する。消してから置き直す
            @unlink($this->path());
            if (!@rename($tmp, $this->path())) {
                @unlink($tmp);

                return;
            }
        }
        @chmod($this->path(), 0600);
    }

    /**
     * allowlist で作り直す。
     * ★未知のキーを保存しない。PII を書き込む経路そのものを作らない。
     *
     * @param array<string,mixed> $entry
     * @return array<string,mixed>
     */
    private static function filter(array $entry): array
    {
        $out = [];
        foreach (self::ENTRY_KEYS as $key) {
            if (!array_key_exists($key, $entry)) {
                continue;
            }
            $value = $entry[$key];
            if (is_string($value) || is_int($value)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
