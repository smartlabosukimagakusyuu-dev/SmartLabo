<?php
/**
 * HP Intake API — preflight 領域の作成と、正式領域の不変検査
 * （HP-ONBOARDING-4H-4 / SSOT §9.10）。
 *
 * ── 位置は固定である ──
 * preflight 領域は **APP_ROOT/preflight/** で確定している。
 * CLI から任意の絶対パスを受け取らない（argv へパスを載せない）。
 *
 * ── 初回導入の順序を機械的に守る ──
 * 正式 `intake-config.php` または正式DBが存在する環境では、
 * `assertInitialInstall()` が失敗する。**init / run はそこで止まる。**
 * これにより SSOT §9.10-9「preflight 撤去 → 正式DB新規作成」の順序を、
 * 運用の心がけではなく検査で担保する。
 *
 * ── 正式設定を読まない ──
 * このクラスも CLI も、正式 `intake-config.php` を **require しない**。
 * 正式側の位置は APP_ROOT からの既定値で導出する（Config の既定と同じ）。
 * 設定ファイル内のコードを実行する経路を作らないためである。
 *
 * ★出力・戻り値へ絶対パスを載せない（理由コードと件数だけを返す）。
 * ★秘密値（鍵）を戻り値・ログへ出さない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Preflight;

use SmartLabo\Intake\Support\PathPolicy;

final class PreflightBuilder
{
    /** APP_ROOT から見た preflight 領域の位置。★固定 */
    public const DIR_NAME = 'preflight';

    /** 正式領域に存在してはならないもの（初回導入の判定に使う） */
    public const PRODUCTION_FORBIDDEN = [
        'intake-config.php',
        'intake.sqlite',
        'intake.sqlite-journal',
        'intake.sqlite-wal',
        'intake.sqlite-shm',
        'ratelimit',
        'backups',
    ];

    /**
     * 正式領域で内容が安定していて、SHA-256 を比べてよいもの。
     * ★`logs/` 配下は外部要因で変わるため対象にしない（§4-4）。
     */
    public const PRODUCTION_HASHED = ['.htaccess'];

    private readonly string $appRoot;

    public function __construct(string $appRoot)
    {
        $this->appRoot = PathPolicy::normalize($appRoot);
    }

    public function root(): string
    {
        return $this->appRoot . '/' . self::DIR_NAME;
    }

    public function productionPrivateRoot(): string
    {
        return $this->appRoot . '/private';
    }

    public function productionDbPath(): string
    {
        return $this->productionPrivateRoot() . '/intake.sqlite';
    }

    public function configPath(): string
    {
        return $this->root() . '/intake-config.php';
    }

    /* ============================================================ 順序の検査 */

    /**
     * 初回導入として実行してよいかを判定する。
     *
     * 正式 `intake-config.php` / 正式DB / journal・wal・shm / ratelimit /
     * backups のいずれかが存在すれば、**順序違反**として失敗させる。
     * ★`status` / `remove` / `verify-empty` はこの検査を通さない
     *   （正式設定を入れたあとでも撤去できる必要があるため）。
     *
     * @return array{ok:bool,error?:string,found?:string}
     */
    public function assertInitialInstall(): array
    {
        $private = $this->productionPrivateRoot();
        foreach (self::PRODUCTION_FORBIDDEN as $name) {
            if (file_exists($private . '/' . $name)) {
                // ★見つかった名前だけを返す。絶対パスは返さない
                return ['ok' => false, 'error' => 'production_already_installed', 'found' => $name];
            }
        }

        return ['ok' => true];
    }

    /* ==================================================== 作成前のパス検査 */

    /**
     * まだ存在しない作成対象を検査する（§3-1 / §3-2）。
     *
     *   1. 字句検査（PathPolicy::checkTextual。存在を要求しない）
     *   2. 最寄りの既存祖先まで遡り、途中に symlink が無いことを確かめる
     *   3. 祖先の realpath を取り、解決後も字句検査を通ることを確かめる
     *   4. 最寄りの既存祖先が **APP_ROOT 自身**であることを確かめる
     *      （＝作るのは1段だけ。深い階層を勝手に作らない）
     *   5. 対象が既に存在しないことを確かめる（既存を再利用しない）
     *
     * @return array{ok:bool,error?:string}
     */
    public function precreateCheck(): array
    {
        $root = $this->root();

        $textual = PathPolicy::checkTextual($root);
        if ($textual['ok'] !== true) {
            return $textual;
        }
        if (file_exists($root) || is_link($root)) {
            return ['ok' => false, 'error' => 'already_exists'];
        }

        // 最寄りの既存祖先を探しながら、途中の symlink を拒否する
        $dir = $root;
        for ($depth = 0; $depth < 32; ++$depth) {
            $parent = PathPolicy::normalize(dirname($dir));
            if ($parent === $dir) {
                return ['ok' => false, 'error' => 'no_existing_parent'];
            }
            $dir = $parent;
            if (is_link($dir)) {
                return ['ok' => false, 'error' => 'symlink_in_parents'];
            }
            if (is_dir($dir)) {
                break;
            }
            if (file_exists($dir)) {
                // ディレクトリでない何かが親の位置にある
                return ['ok' => false, 'error' => 'parent_not_directory'];
            }
        }

        if (!PathPolicy::isSame($dir, $this->appRoot)) {
            // ★APP_ROOT の直下に1段だけ作る。それ以外は作らない
            return ['ok' => false, 'error' => 'parent_is_not_app_root'];
        }

        $real = realpath($dir);
        if ($real === false) {
            return ['ok' => false, 'error' => 'missing'];
        }
        $resolved = PathPolicy::checkTextual($real);
        if ($resolved['ok'] !== true) {
            return $resolved;
        }
        // 祖先をルートまで遡り、symlink が無いことを確かめる
        $walk = PathPolicy::normalize($real);
        for ($depth = 0; $depth < 32; ++$depth) {
            $parent = PathPolicy::normalize(dirname($walk));
            if ($parent === $walk) {
                break;
            }
            $walk = $parent;
            if (is_link($walk)) {
                return ['ok' => false, 'error' => 'symlink_in_parents'];
            }
        }

        return ['ok' => true];
    }

    /**
     * 作成後の再検査（§3-4。TOCTOU の検出）。
     *
     * @return array{ok:bool,error?:string}
     */
    public function postCreateCheck(): array
    {
        $root = $this->root();

        if (is_link($root)) {
            return ['ok' => false, 'error' => 'symlink'];
        }
        $checked = PathPolicy::checkDir($root);
        if ($checked['ok'] !== true) {
            return $checked;
        }
        // ★検査と作成の間に差し替えられていれば、ここで一致しない
        if (!PathPolicy::isSame((string)$checked['dir'], $root)) {
            return ['ok' => false, 'error' => 'realpath_mismatch'];
        }
        if (!self::hasMode($root, 0700)) {
            return ['ok' => false, 'error' => 'dir_mode'];
        }
        foreach (PreflightArea::CHILDREN as $child) {
            $path = $root . '/' . $child;
            if (!is_dir($path) || is_link($path)) {
                return ['ok' => false, 'error' => 'child_missing'];
            }
            if (!self::hasMode($path, 0700)) {
                return ['ok' => false, 'error' => 'child_mode'];
            }
        }
        $cfg = $this->configPath();
        if (!is_file($cfg) || is_link($cfg)) {
            return ['ok' => false, 'error' => 'config_missing'];
        }
        if (!self::hasMode($cfg, 0600)) {
            return ['ok' => false, 'error' => 'config_mode'];
        }

        return ['ok' => true];
    }

    /* ================================================================ 作成 */

    /**
     * preflight 領域を作る（§3-3）。
     *
     * ★umask 0077 ／ ディレクトリ 0700 ／ 設定ファイルは `xb` で排他的に作成し 0600。
     * ★鍵はこの場で2つ別々に生成する。**戻り値にもログにも出さない。**
     * ★失敗しても**自動で削除しない**（撤去は status → dry-run → 承認 → apply）。
     *
     * @return array{ok:bool,error?:string}
     */
    public function create(): array
    {
        $pre = $this->precreateCheck();
        if ($pre['ok'] !== true) {
            return $pre;
        }

        $old = umask(0077);
        try {
            $root = $this->root();
            if (!@mkdir($root, 0700)) {   // ★recursive を使わない
                return ['ok' => false, 'error' => 'mkdir_failed'];
            }
            @chmod($root, 0700);

            foreach (PreflightArea::CHILDREN as $child) {
                if (!@mkdir($root . '/' . $child, 0700)) {
                    return ['ok' => false, 'error' => 'mkdir_child_failed'];
                }
                @chmod($root . '/' . $child, 0700);
            }

            $written = $this->writeConfig();
            if ($written['ok'] !== true) {
                return $written;
            }
        } finally {
            umask($old);
        }

        return $this->postCreateCheck();
    }

    /**
     * preflight 専用の設定ファイルを排他的に作る（§3-5 / §7）。
     *
     * ★`fopen('xb')` は O_CREAT|O_EXCL 相当。**既存なら上書きせず失敗**する。
     * ★鍵は `random_bytes(32)` を2回。**別々の値**にする。
     * ★通知設定を書かない。retention / backup のフラグは false を明記する。
     * ★管理者情報は書かない（run のプロセス内でだけ作る）。
     *
     * @return array{ok:bool,error?:string}
     */
    private function writeConfig(): array
    {
        $path = $this->configPath();

        $fh = @fopen($path, 'xb');
        if ($fh === false) {
            return ['ok' => false, 'error' => 'config_exists_or_unwritable'];
        }
        @chmod($path, 0600);

        // ★2つの鍵は別々に生成する（同じ値を使い回さない）
        $ipHmacKey = bin2hex(random_bytes(32));
        $encKey    = bin2hex(random_bytes(32));

        $body = "<?php\n"
            . "/**\n"
            . " * HP Intake API — preflight 専用設定（自動生成・架空データ専用）。\n"
            . " * ★このファイルは preflight 領域の中だけにある。正式設定ではない。\n"
            . " * ★領域ごと撤去すれば、この設定と鍵も一緒に消える。\n"
            . " * ★Git へ入れない（preflight 領域は追跡対象外）。\n"
            . " */\n"
            . "declare(strict_types=1);\n\n"
            . "return [\n"
            . "    'ip_hmac_key' => '" . $ipHmacKey . "',\n"
            . "    'enc_key'     => '" . $encKey . "',\n"
            . "    'retention_actions_enabled' => false,\n"
            . "    'backup_policy_confirmed'   => false,\n"
            . "];\n";

        $ok = fwrite($fh, $body);
        fclose($fh);
        // ★鍵をメモリから早めに手放す（戻り値にも入れない）
        unset($ipHmacKey, $encKey, $body);

        if ($ok === false) {
            return ['ok' => false, 'error' => 'config_write_failed'];
        }
        @chmod($path, 0600);

        return self::hasMode($path, 0600) ? ['ok' => true] : ['ok' => false, 'error' => 'config_mode'];
    }

    /* ============================================================ 設定の組立 */

    /**
     * `Config::load()` へ渡す overrides を組み立てる。
     *
     * ★**正式 `intake-config.php` を require しない。**
     *   overrides を非空で渡すことで、Config は正式ファイルを読まない
     *   （src/Config.php の load() を参照）。
     * ★preflight 領域の設定ファイルだけを読む。
     * ★`preflight_root` は**設定しない**（private_root と重なるため）。
     *
     * @return array<string,mixed>
     */
    public function configOverrides(): array
    {
        $root = $this->root();
        $file = [];
        if (is_file($this->configPath())) {
            $loaded = require $this->configPath();
            if (is_array($loaded)) {
                $file = $loaded;
            }
        }

        return [
            'app_root'       => $this->appRoot,
            'private_root'   => $root,
            'db_path'        => $root . '/intake.sqlite',
            'rate_limit_dir' => $root . '/ratelimit',
            'log_path'       => $root . '/logs/intake.log',
            'backup_dir'     => $root . '/backups',
            'ip_hmac_key'    => (string)($file['ip_hmac_key'] ?? ''),
            'enc_key'        => (string)($file['enc_key'] ?? ''),
            // ★http は 127.0.0.1 のみ許される（Config::originAcceptable）
            'allowed_origins' => [PreflightRunner::ORIGIN],
            'require_https'   => false,
            'retention_actions_enabled' => false,
            'backup_policy_confirmed'   => false,
            // ★通知は設定しない（宛先・差出人を持たせない）
        ];
    }

    /**
     * 解決後の全パスが preflight 領域の内側であることを表明検査する（§8 E-3）。
     *
     * @param array<string,mixed> $overrides
     * @return array{ok:bool,error?:string,key?:string}
     */
    public function assertInsideRoot(array $overrides): array
    {
        $root = $this->root();
        foreach (['private_root', 'db_path', 'rate_limit_dir', 'log_path', 'backup_dir'] as $key) {
            $value = (string)($overrides[$key] ?? '');
            if ($value === '') {
                return ['ok' => false, 'error' => 'path_missing', 'key' => $key];
            }
            $path = PathPolicy::normalize($value);
            if (PathPolicy::isSame($path, $root)) {
                continue;   // private_root は領域そのもの
            }
            if (!PathPolicy::isInside($root, $path)) {
                return ['ok' => false, 'error' => 'path_outside_preflight', 'key' => $key];
            }
        }

        return ['ok' => true];
    }

    /* ==================================================== 正式領域の不変検査 */

    /**
     * 正式領域と配置物の状態を取る（§4-2 / §4-3）。
     *
     * ★決め打ちの期待値は持たない。**run の直前に取った baseline と比べる**。
     * ★`logs/` 配下の本文はハッシュしない（外部要因で変わる。§4-4）。
     *
     * @return array{
     *   entries:list<string>, hashes:array<string,string>,
     *   forbidden:list<string>, logs:array<string,array{size:int,mode:?string}>
     * }
     */
    public function productionSnapshot(): array
    {
        $private = $this->productionPrivateRoot();

        $entries = [];
        if (is_dir($private)) {
            foreach (scandir($private) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $entries[] = $name . (is_dir($private . '/' . $name) ? '/' : '');
            }
        }
        sort($entries);

        // 内容が安定しているものだけ SHA-256 を取る
        $hashes = [];
        foreach (self::PRODUCTION_HASHED as $name) {
            $path = $private . '/' . $name;
            if (is_file($path)) {
                $hashes['private/' . $name] = (string)hash_file('sha256', $path);
            }
        }
        // 配置物（src / bin）は全件比較する（代表判断 J-h）
        foreach (['src', 'bin'] as $dirName) {
            $base = $this->appRoot . '/' . $dirName;
            foreach (self::files($base) as $path) {
                $rel = $dirName . '/' . ltrim(substr(PathPolicy::normalize($path), strlen(PathPolicy::normalize($base))), '/');
                $hashes[$rel] = (string)hash_file('sha256', $path);
            }
        }
        ksort($hashes);

        // 出現してはならないもの
        $forbidden = [];
        foreach (self::PRODUCTION_FORBIDDEN as $name) {
            if (file_exists($private . '/' . $name)) {
                $forbidden[] = $name;
            }
        }

        // ログは本文をハッシュせず、存在・権限・サイズだけを見る（§4-4）
        $logs = [];
        $logDir = $private . '/logs';
        if (is_dir($logDir)) {
            foreach (scandir($logDir) ?: [] as $name) {
                if ($name === '.' || $name === '..' || !is_file($logDir . '/' . $name)) {
                    continue;
                }
                $logs[$name] = [
                    'size' => (int)filesize($logDir . '/' . $name),
                    // ★Windows は POSIX 権限を持たない。比較対象にしない
                    'mode' => DIRECTORY_SEPARATOR !== '/'
                        ? null
                        : substr(sprintf('%o', fileperms($logDir . '/' . $name)), -4),
                ];
            }
            ksort($logs);
        }

        return ['entries' => $entries, 'hashes' => $hashes, 'forbidden' => $forbidden, 'logs' => $logs];
    }

    /**
     * baseline と比べる（§4）。
     *
     * ★STOP になるのは
     *     ・一覧の増減
     *     ・安定ファイルのハッシュ変化
     *     ・出現してはならないものの出現
     *     ・ログの**消失**または**権限 600 の崩れ**
     *   である。ログのサイズ増減は**記録するだけ**（§4-4 / 最終修正3）。
     *
     * @param array{entries:list<string>,hashes:array<string,string>,forbidden:list<string>,logs:array<string,array{size:int,mode:?string}>} $before
     * @param array{entries:list<string>,hashes:array<string,string>,forbidden:list<string>,logs:array<string,array{size:int,mode:?string}>} $after
     * @return array{ok:bool,problems:list<string>,notes:list<string>}
     */
    public static function compareSnapshots(array $before, array $after): array
    {
        $problems = [];
        $notes    = [];

        foreach (array_diff($after['entries'], $before['entries']) as $name) {
            $problems[] = 'private に想定外の出現: ' . $name;
        }
        foreach (array_diff($before['entries'], $after['entries']) as $name) {
            $problems[] = 'private から消失: ' . $name;
        }

        foreach ($after['forbidden'] as $name) {
            $problems[] = '出現してはならないものがある: ' . $name;
        }

        foreach ($before['hashes'] as $rel => $hash) {
            if (!array_key_exists($rel, $after['hashes'])) {
                $problems[] = '配置物が消えた: ' . $rel;
                continue;
            }
            if (!hash_equals($hash, $after['hashes'][$rel])) {
                $problems[] = '配置物の内容が変わった: ' . $rel;
            }
        }
        foreach (array_keys($after['hashes']) as $rel) {
            if (!array_key_exists($rel, $before['hashes'])) {
                $problems[] = '配置物が増えた: ' . $rel;
            }
        }

        foreach ($before['logs'] as $name => $info) {
            if (!array_key_exists($name, $after['logs'])) {
                // ★消失は STOP（最終修正3）
                $problems[] = 'ログが消えた: ' . $name;
                continue;
            }
            $now = $after['logs'][$name];
            // ★「600 の崩れ」＝実行前は 600 だったのに、実行後に崩れた場合だけを STOP とする。
            //   もともと 600 でないものは preflight が壊したのではないため、記録に留める。
            if ($info['mode'] !== null && $now['mode'] !== null) {
                if ($info['mode'] === '0600' && $now['mode'] !== '0600') {
                    $problems[] = 'ログの権限が 600 から崩れた: ' . $name;
                } elseif ($info['mode'] !== '0600') {
                    $notes[] = 'ログの権限が 600 でない（preflight 実行前から）: '
                        . $name . '（' . $info['mode'] . '）';
                }
            }
            if ($now['size'] !== $info['size']) {
                // ★増減はどちらも記録だけ。ローテーション等の外部要因を誤検出しない
                $notes[] = 'ログのサイズが変化: ' . $name
                    . '（' . $info['size'] . ' → ' . $now['size'] . ' bytes）';
            }
        }
        foreach (array_keys($after['logs']) as $name) {
            if (!array_key_exists($name, $before['logs'])) {
                $notes[] = 'ログが増えた: ' . $name;
            }
        }

        return ['ok' => $problems === [], 'problems' => $problems, 'notes' => $notes];
    }

    /* ================================================================ 補助 */

    /** 権限の下位12bit が期待どおりか */
    private static function hasMode(string $path, int $mode): bool
    {
        $perms = @fileperms($path);
        if ($perms === false) {
            return false;
        }
        // ★Windows は POSIX 権限を持たないため、ローカル確認では検査しない
        if (DIRECTORY_SEPARATOR !== '/') {
            return true;
        }

        return ($perms & 0o7777) === $mode;
    }

    /**
     * ディレクトリ配下のファイルを列挙する（symlink をたどらない）。
     *
     * @return list<string>
     */
    private static function files(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        $it  = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $entry) {
            if ($entry->isFile() && !$entry->isLink()) {
                $out[] = $entry->getPathname();
            }
        }
        sort($out);

        return $out;
    }
}
