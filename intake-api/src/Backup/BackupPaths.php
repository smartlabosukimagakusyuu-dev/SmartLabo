<?php
/**
 * HP Intake API — バックアップの置き場所とファイル名の規則（SSOT v1.11 §9.5 / §9.5.1）。
 *
 * ここは「どこへ書いてよいか」だけを決める。DB も filesystem も触らない
 * （`is_dir` 等の存在確認は行うが、作成・削除・書き込みはしない）。
 *
 * ★設定値をそのまま信じない。**使う直前に毎回検査し直す**。
 *   設定を書き換えられた・symlink を差し替えられた場合でも、
 *   公開領域やホーム直下へ DB のコピーを落とさないための最後の砦である。
 * ★本番の正確な絶対パスは 4H で XServer 実機を確認してから確定する。
 *   4G ではローカルの一時ディレクトリで同じ規則を検証するだけである。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Backup;

use SmartLabo\Intake\Support\PathPolicy;

final class BackupPaths
{
    /** バックアップファイルの接頭辞（SSOT v1.11 §9.5.1） */
    public const FILE_PREFIX = 'intake-';

    /** 拡張子は固定。これ以外の名前は作らないし、削除・復元の対象にもしない */
    public const FILE_EXT = '.sqlite';

    /**
     * 正式なバックアップファイル名。
     * `intake-YYYYMMDD-HHMMSS-<random8>.sqlite`
     *
     * ★店舗名・案件番号・メール・token を含めない（SSOT v1.11 §9.5.1）。
     * ★連番だけにしない。予測できる名前は列挙の手がかりになる。
     */
    public const FILE_PATTERN = '/^intake-\d{8}-\d{6}-[0-9a-f]{8}\.sqlite$/';

    /** 非PII の一覧ファイル（ファイル名・作成日時・サイズ・SHA-256・版のみ） */
    public const MANIFEST_NAME = 'manifest.json';

    /** 排他ロック用（中身は空。内容を読まない） */
    public const LOCK_NAME = '.lock';

    /** 作成途中の一時ファイル。rename に成功するまでこの名前で置く */
    public const TEMP_PREFIX = '.tmp-';
    public const TEMP_EXT    = '.part';

    /** 復元確認（restore drill）が使う一時ディレクトリ */
    public const DRILL_PREFIX = '.drill-';

    /**
     * この語をパスの構成要素に含む場所へは置かない。
     * ★実体は `Support\PathPolicy::FORBIDDEN_SEGMENTS`（4H-R0 で集約）。
     *   ここは「バックアップの言葉」で参照できる別名を残すだけである。
     */
    public const FORBIDDEN_SEGMENTS = PathPolicy::FORBIDDEN_SEGMENTS;

    /**
     * 保存先ディレクトリを検査する（実体は PathPolicy）。
     *
     * ★4H-R0 から、パスの安全規則は `Support\PathPolicy` に集約した。
     *   APP_ROOT・バックアップ置き場・preflight 領域が**同じ規則**で守られる。
     *   ここは「バックアップの言葉」で呼べる入口を残すだけである。
     *
     * @return array{ok:bool,error?:string,dir?:string}
     */
    public static function checkDir(?string $raw): array
    {
        return PathPolicy::checkDir($raw);
    }

    /** 区切りを `/` に揃え、重複と末尾を落とす（値の意味は変えない） */
    public static function normalize(string $path): string
    {
        return PathPolicy::normalize($path);
    }

    public static function isAbsolute(string $path): bool
    {
        return PathPolicy::isAbsolute($path);
    }

    /** ルートを除いた構成要素 @return list<string> */
    public static function segments(string $normalizedPath): array
    {
        return PathPolicy::segments($normalizedPath);
    }

    /**
     * 許可ディレクトリ内の正式なバックアップファイルかを検査する。
     *
     * ★呼び出し側が組み立てたパスを受け取らない。**ディレクトリとファイル名**を別々に受け、
     *   ここで結合する。`../` 入りの「ファイル名」を通さないため。
     *
     * @return array{ok:bool,error?:string,path?:string}
     */
    public static function checkFile(string $canonicalDir, string $name): array
    {
        if (preg_match(self::FILE_PATTERN, $name) !== 1) {
            return ['ok' => false, 'error' => 'not_a_backup_name'];
        }

        $path = $canonicalDir . '/' . $name;
        if (!is_file($path)) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (is_link($path)) {
            return ['ok' => false, 'error' => 'symlink'];
        }

        $real = realpath($path);
        if ($real === false) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (!self::isInside($canonicalDir, self::normalize($real))) {
            return ['ok' => false, 'error' => 'outside_dir'];
        }

        return ['ok' => true, 'path' => self::normalize($real)];
    }

    /**
     * `$path` が `$dir` の中にあるか。
     * ★接頭辞比較だけで済ませない。`/a/backups2` を `/a/backups` の中と誤判定しないよう
     *   区切り文字まで含めて比べる。
     */
    public static function isInside(string $dir, string $path): bool
    {
        return PathPolicy::isInside($dir, $path);
    }

    /**
     * 新しいバックアップファイル名を作る（SSOT v1.11 §9.5.1）。
     * @param int $timestamp UNIX 時刻（Clock 経由で渡す。ここで time() を呼ばない）
     */
    public static function makeName(int $timestamp): string
    {
        return self::FILE_PREFIX . gmdate('Ymd-His', $timestamp) . '-' . bin2hex(random_bytes(4)) . self::FILE_EXT;
    }

    /** ファイル名から作成時刻（UNIX）を読む。名前が規則外なら null */
    public static function timestampOf(string $name): ?int
    {
        if (preg_match('/^intake-(\d{8})-(\d{6})-[0-9a-f]{8}\.sqlite$/', $name, $m) !== 1) {
            return null;
        }
        $ts = strtotime($m[1] . 'T' . substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2) . ':' . substr($m[2], 4, 2) . 'Z');

        return $ts === false ? null : $ts;
    }
}
