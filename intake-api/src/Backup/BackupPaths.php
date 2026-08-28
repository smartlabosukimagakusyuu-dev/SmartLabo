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
     * ★Web から到達できる可能性のある名前を、部分一致ではなく**構成要素の完全一致**で見る
     *   （`/home/user/publications/` を誤って拒否しないため）。
     */
    public const FORBIDDEN_SEGMENTS = ['public_html', 'public', 'htdocs', 'www', 'wwwroot', 'web'];

    /**
     * 保存先ディレクトリを検査する。
     *
     * 通す条件（すべて満たすこと）:
     *   1. 設定されている（空でない）
     *   2. 絶対パス
     *   3. `..` を含まない
     *   4. 公開領域を思わせる構成要素を含まない
     *   5. ルート直下・ホーム直下でない（深さ2以上）
     *   6. 実在するディレクトリで、symlink でない
     *   7. realpath でも 2〜5 を満たす（symlink で外へ逃げていない）
     *
     * @return array{ok:bool,error?:string,dir?:string}
     */
    public static function checkDir(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['ok' => false, 'error' => 'not_configured'];
        }
        $value = trim($raw);

        // 制御文字・NUL を含む値は設定事故。開かずに落とす
        if (preg_match('/[\x00-\x1f]/', $value) === 1) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        $textual = self::checkTextualDir($value);
        if ($textual['ok'] !== true) {
            return $textual;
        }

        if (!is_dir($value)) {
            return ['ok' => false, 'error' => 'missing'];
        }
        if (is_link(rtrim($value, '/\\'))) {
            return ['ok' => false, 'error' => 'symlink'];
        }

        $real = realpath($value);
        if ($real === false) {
            return ['ok' => false, 'error' => 'missing'];
        }

        // ★symlink を経由して公開領域・ホーム直下へ出ていないかを、解決後の実体でも見る
        $resolved = self::checkTextualDir($real);
        if ($resolved['ok'] !== true) {
            return $resolved;
        }
        if (!is_writable($real)) {
            return ['ok' => false, 'error' => 'not_writable'];
        }

        return ['ok' => true, 'dir' => self::normalize($real)];
    }

    /**
     * 文字列としての検査だけを行う（存在確認をしない）。
     * @return array{ok:bool,error?:string}
     */
    private static function checkTextualDir(string $value): array
    {
        $path = self::normalize($value);

        if (!self::isAbsolute($path)) {
            return ['ok' => false, 'error' => 'relative'];
        }

        $segments = self::segments($path);
        foreach ($segments as $segment) {
            if ($segment === '..' || $segment === '.') {
                return ['ok' => false, 'error' => 'traversal'];
            }
            if (in_array(strtolower($segment), self::FORBIDDEN_SEGMENTS, true)) {
                return ['ok' => false, 'error' => 'public_area'];
            }
        }

        // ★ホーム直下を先に見る。`/root` のように深さ1でもあるため、
        //   「浅い」より「ホーム」の方が理由として正確である
        if (self::looksLikeHomeRoot($path)) {
            return ['ok' => false, 'error' => 'home_root'];
        }
        // ルート（`/` `C:/`）と、その直下1階層は認めない
        if (count($segments) < 2) {
            return ['ok' => false, 'error' => 'too_shallow'];
        }

        return ['ok' => true];
    }

    /**
     * ホームディレクトリそのもの（`/home/xxx` `/root` `/Users/xxx` `C:/Users/xxx`）か。
     * ★ホーム**直下**は、うっかり同期・共有の対象になりやすい。配下の専用ディレクトリを使う。
     */
    private static function looksLikeHomeRoot(string $path): bool
    {
        $lower = strtolower(rtrim($path, '/'));
        foreach (['#^/home/[^/]+$#', '#^/root$#', '#^/users/[^/]+$#', '#^[a-z]:/users/[^/]+$#'] as $re) {
            if (preg_match($re, $lower) === 1) {
                return true;
            }
        }
        foreach (['HOME', 'USERPROFILE'] as $env) {
            $home = getenv($env);
            if (is_string($home) && $home !== ''
                && strtolower(rtrim(self::normalize($home), '/')) === $lower) {
                return true;
            }
        }

        return false;
    }

    /** 区切りを `/` に揃え、重複と末尾を落とす（値の意味は変えない） */
    public static function normalize(string $path): string
    {
        $p = preg_replace('#[\\\\/]+#', '/', $path) ?? $path;
        if (strlen($p) > 1) {
            $p = rtrim($p, '/');
        }
        // `C:` だけになった場合は `C:/` に戻す（ルート判定を壊さない）
        if (preg_match('#^[A-Za-z]:$#', $p) === 1) {
            $p .= '/';
        }

        return $p;
    }

    public static function isAbsolute(string $path): bool
    {
        return strncmp($path, '/', 1) === 0 || preg_match('#^[A-Za-z]:/#', $path) === 1;
    }

    /** ルートを除いた構成要素 @return list<string> */
    public static function segments(string $normalizedPath): array
    {
        $rest = preg_replace('#^([A-Za-z]:)?/#', '', $normalizedPath) ?? $normalizedPath;

        return $rest === '' ? [] : array_values(array_filter(explode('/', $rest), static fn ($s) => $s !== ''));
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
        $d = rtrim(self::normalize($dir), '/') . '/';
        $p = self::normalize($path);

        // Windows は大文字小文字を区別しないため、比較だけ小文字へ落とす
        if (DIRECTORY_SEPARATOR === '\\') {
            $d = strtolower($d);
            $p = strtolower($p);
        }

        return strncmp($p, $d, strlen($d)) === 0 && strlen($p) > strlen($d);
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
