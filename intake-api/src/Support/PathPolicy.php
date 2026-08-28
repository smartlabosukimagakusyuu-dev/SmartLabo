<?php
/**
 * HP Intake API — パスの安全規則（HP-ONBOARDING-4H-R0 / SSOT v1.12 §10.11）。
 *
 * 「どこへ書いてよいか」「どこを読んでよいか」の判定を**1か所へ集める**。
 * APP_ROOT・バックアップ置き場・preflight 領域が、同じ規則で守られるようにする。
 *
 * ★ここは判定だけを行う。作成・削除・書き込みをしない
 *   （`is_dir` / `realpath` の存在確認は行う）。
 * ★設定値をそのまま信じない。**使う直前に毎回検査し直す**。
 * ★Web の入力（URL・Cookie・POST・query・ヘッダー）を読まない。
 *   このファイルはスーパーグローバルへ触れない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Support;

final class PathPolicy
{
    /**
     * この語を**構成要素として**含む場所は、公開領域とみなして拒否する。
     * ★部分一致ではなく構成要素の完全一致で見る
     *   （`/home/user/publications/` を誤って拒否しないため）。
     */
    public const FORBIDDEN_SEGMENTS = ['public_html', 'public', 'htdocs', 'www', 'wwwroot', 'web'];

    /**
     * ディレクトリとして受け付けてよいかを、**文字列だけ**で判定する。
     *
     * 通す条件:
     *   1. 空でない・制御文字を含まない
     *   2. 絶対パス
     *   3. `.` `..` を構成要素に含まない
     *   4. 公開領域を思わせる構成要素を含まない
     *   5. ホーム直下でない
     *   6. ルート直下でない（構成要素2つ以上）
     *
     * @return array{ok:bool,error?:string}
     */
    public static function checkTextual(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['ok' => false, 'error' => 'not_configured'];
        }
        $value = trim($raw);

        // 制御文字・NUL を含む値は設定事故。開かずに落とす
        if (preg_match('/[\x00-\x1f]/', $value) === 1) {
            return ['ok' => false, 'error' => 'invalid'];
        }

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
        if (count($segments) < 2) {
            return ['ok' => false, 'error' => 'too_shallow'];
        }

        return ['ok' => true];
    }

    /**
     * 実在するディレクトリとして受け付けてよいかを判定し、正規化した実体パスを返す。
     *
     * 文字列の検査に加えて:
     *   7. 実在するディレクトリで、symlink でない
     *   8. realpath 解決後も 2〜6 を満たす（symlink で外へ逃げていない）
     *   9. （要求時）書き込める
     *
     * @return array{ok:bool,error?:string,dir?:string}
     */
    public static function checkDir(?string $raw, bool $requireWritable = true): array
    {
        $textual = self::checkTextual($raw);
        if ($textual['ok'] !== true) {
            return $textual;
        }
        $value = trim((string)$raw);

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
        $resolved = self::checkTextual($real);
        if ($resolved['ok'] !== true) {
            return $resolved;
        }
        if ($requireWritable && !is_writable($real)) {
            return ['ok' => false, 'error' => 'not_writable'];
        }

        return ['ok' => true, 'dir' => self::normalize($real)];
    }

    /**
     * ホームディレクトリそのもの（`/home/xxx` `/root` `/Users/xxx` `C:/Users/xxx`）か。
     * ★ホーム**直下**は、うっかり同期・共有の対象になりやすい。配下の専用ディレクトリを使う。
     */
    public static function looksLikeHomeRoot(string $path): bool
    {
        $lower = strtolower(rtrim(self::normalize($path), '/'));
        foreach (['#^/home/[^/]+$#', '#^/root$#', '#^/users/[^/]+$#', '#^[a-z]:/users/[^/]+$#'] as $re) {
            if (preg_match($re, $lower) === 1) {
                return true;
            }
        }
        // ★環境変数は「いまのホーム」を知るためだけに読む。パスの決定には使わない
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

    /** 2つのパスが同じ場所を指すか（正規化のみ。realpath は呼び出し側で） */
    public static function isSame(string $a, string $b): bool
    {
        $x = self::normalize($a);
        $y = self::normalize($b);

        if (DIRECTORY_SEPARATOR === '\\') {
            return strtolower($x) === strtolower($y);
        }

        return $x === $y;
    }
}
