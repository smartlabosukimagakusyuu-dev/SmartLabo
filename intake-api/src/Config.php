<?php
/**
 * HP Intake API — 設定。
 *
 * SSOT: docs/website/HP_ONBOARDING_INTAKE_DATA_MODEL_V1.md v1.2
 *
 * ★秘密値（IP HMAC鍵・暗号鍵）は環境変数、または public_html の外に置く
 *   private/intake-config.php からのみ読む。**未設定なら起動しない（fail closed）**。
 * ★このファイルに既定の鍵を書かない。
 *
 * 4H-R0（SSOT v1.12 §10.11）で、配置の境界を**明示的に分離**した。
 *
 *   APP_ROOT      アプリ本体の置き場所（`AppRoot` が解決・検査する）
 *   src_root      APP_ROOT/src（固定）
 *   private_root  設定・DB・ログ・rate limit の親（既定 APP_ROOT/private）
 *   db_path / log_path / rate_limit_dir / backup_dir / preflight_root
 *
 * ★docroot と APP_ROOT が**兄弟である必要はない**。
 *   XServer のサブドメインは public_html の下に作られるため、
 *   docroot の親（＝public_html）へ src / private を置かせない。
 * ★実設定ファイルは **APP_ROOT/private/intake-config.php** から読む。
 *   `private_root` はその中で**データの置き場所**を移すためのものであり、
 *   設定ファイル自身の位置は動かせない（読む前に決まらないため）。
 * ★絶対パスを応答・ログ・HTML・JSON へ出さない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake;

use SmartLabo\Intake\Support\PathPolicy;

final class Config
{
    /** Cookie 名。★店舗名・案件番号を含めない（SSOT §2.6-9） */
    public const COOKIE_NAME = 'sl_intake_sid';

    /**
     * 管理画面の Cookie 名（SSOT §2.7-4）。
     * ★`admin` の語・店舗名・案件番号を含めない。店舗向けとも別名にする。
     */
    public const ADMIN_COOKIE_NAME = 'sl_op_sid';

    /** 管理 session の idle 期限（秒）＝30分（SSOT §10.8） */
    public const ADMIN_SESSION_IDLE_TTL = 30 * 60;

    /** 管理 session の絶対期限（秒）＝8時間（SSOT §10.8） */
    public const ADMIN_SESSION_ABSOLUTE_TTL = 8 * 60 * 60;

    /** token / session secret の平文長（base64url 43文字。SSOT §4.1） */
    public const SECRET_CHARS = 43;

    /** token 有効期限（秒）＝14日（SSOT §4.3） */
    public const TOKEN_TTL = 14 * 24 * 60 * 60;

    /** session 有効期限（秒）＝最終利用から24時間（SSOT §2.6-6） */
    public const SESSION_IDLE_TTL = 24 * 60 * 60;

    /** session 絶対有効期限（秒）＝発行から7日（SSOT §2.6-7） */
    public const SESSION_ABSOLUTE_TTL = 7 * 24 * 60 * 60;

    /** JSON body の上限（SSOT §0.2） */
    public const MAX_BODY_BYTES = 1048576;

    /**
     * 通知メールのアドレスに含めてはならない文字（SSOT v1.12 §9.11）。
     * ★複数宛先・表示名・引用符・エスケープの入口を1つも通さない。
     */
    public const ADDRESS_FORBIDDEN = [',', ';', '<', '>', '"', "'", '(', ')', '[', ']', ':'];

    /** rate limit（SSOT §0.2） [上限回数, 期間秒] */
    public const RATE_LIMITS = [
        'token_start' => [5, 600],
        'answer_save' => [60, 600],
        'submit'      => [5, 600],
        'drive_confirm' => [5, 600],
        // 管理ログイン: HMAC化IP単位で 10分5回（SSOT §10.8）
        'admin_login' => [5, 600],
        // ご案内リンクの再発行: 案件 ＋ HMAC化IP 単位で 10分5回（SSOT v1.6 §4.4.1）
        'token_reissue' => [5, 600],
    ];

    public function __construct(
        public readonly string $dbPath,
        public readonly string $ipHmacKey,
        public readonly string $encKey,
        public readonly array $allowedOrigins,
        public readonly string $rateLimitDir,
        public readonly ?string $logPath,
        public readonly bool $requireHttps,
        /**
         * アプリ本体の置き場所（SSOT v1.12 §10.11）。
         * ★`AppRoot::require()` が検査済みの絶対パスだけを入れる。
         *   公開領域内・相対・不在・symlink 逸脱は load() で例外になる。
         */
        public readonly string $appRoot,
        /** APP_ROOT/src（固定）。オートローダが読む場所 */
        public readonly string $srcRoot,
        /** 設定・DB・ログ・rate limit の親（既定 APP_ROOT/private） */
        public readonly string $privateRoot,
        /**
         * 管理者ID（SSOT §10.8-1）。未設定なら null。
         * ★null のときは管理画面を一切動かさない（fail closed）。
         */
        public readonly ?string $adminId = null,
        /**
         * password_hash() が作った hash（SSOT §10.8-2）。未設定なら null。
         * ★平文パスワードをここへ入れてはならない。
         */
        public readonly ?string $adminPasswordHash = null,
        /**
         * 保持期限による削除操作を有効にしてよいか（SSOT v1.7 §9.8-1）。
         * ★既定は **false**。設定していない環境では削除経路そのものを動かさない。
         */
        public readonly bool $retentionActionsEnabled = false,
        /**
         * 本番バックアップの世代・削除方針が確定済みか（SSOT v1.7 §9.8-2 / v1.11 §9.9）。
         * ★既定は **false**。4G のローカル実装だけでは true にしない。
         *   4H で XServer 上の絶対パス・権限・実測が済むまで false のままにする
         *   （SSOT v1.11 §9.9-3）。古いバックアップから、消したはずの回答が
         *   復元できてしまう運用を作らないため。
         */
        public readonly bool $backupPolicyConfirmed = false,
        /**
         * バックアップの保存先ディレクトリ（SSOT v1.11 §9.5）。
         * ★既定は **null（未設定）**。未設定ならバックアップ経路そのものを動かさない。
         * ★**絶対パスのみ**。相対パス・public_html 配下・ホーム直下・ルートは
         *   `BackupPaths::checkDir()` が拒否する。ここでは値を持つだけで検査しない
         *   （検査は使う直前に必ずやり直す）。
         * ★本番の正確な絶対パスは 4H で XServer 実機を確認してから確定する。
         */
        public readonly ?string $backupDir = null,
        /**
         * 本番配置後の通し確認だけに使う領域（SSOT v1.12 §9.10）。未設定なら null。
         * ★**正式DB・正式バックアップと完全に分ける。** 架空データ専用である。
         * ★正式な private_root の中・配下・同一を認めない（load() で拒否する）。
         */
        public readonly ?string $preflightRoot = null,
        /**
         * 提出通知の宛先（SSOT v1.12 §9.11）。未設定なら通知しない（fail closed）。
         * ★1宛先のみ。改行・複数宛先・ヘッダー注入は load() で拒否する。
         */
        public readonly ?string $notificationRecipient = null,
        /**
         * 提出通知の差出人。未設定なら通知しない。
         * ★XServer で正式に使える自社ドメインのアドレスを 4H で設定する。
         */
        public readonly ?string $notificationFrom = null,
    ) {
    }

    /** 提出通知を実際に送ってよいか。★宛先と差出人が両方そろったときだけ */
    public function notificationEnabled(): bool
    {
        return $this->notificationRecipient !== null && $this->notificationFrom !== null;
    }

    /**
     * 破壊的な保持削除を実行してよいか（SSOT v1.7 §9.8）。
     * ★2つのフラグが**両方**真のときだけ。どちらか欠ければ実行しない（fail closed）。
     */
    public function retentionEnabled(): bool
    {
        return $this->retentionActionsEnabled && $this->backupPolicyConfirmed;
    }

    /** 管理画面を動かしてよいか。★資格情報が揃っていなければ動かさない */
    public function adminEnabled(): bool
    {
        return $this->adminId !== null
            && $this->adminId !== ''
            && $this->adminPasswordHash !== null
            && $this->adminPasswordHash !== '';
    }

    /**
     * APP_ROOT を解決 → 環境変数 → APP_ROOT/private/intake-config.php の順で読む。
     *
     * ★APP_ROOT の解決は `AppRoot::require()` が行い、**不正なら例外**にする
     *   （既定へ黙って落ちない。SSOT v1.12 §10.11-6）。
     * ★Web の入力から APP_ROOT を変えられない（`AppRoot` はスーパーグローバルを読まない）。
     *
     * @param array<string,mixed> $overrides テスト専用の直接指定
     */
    public static function load(array $overrides = []): self
    {
        // ★最初に APP_ROOT を決める。ここが決まらなければ設定ファイルの場所も決まらない
        $appRoot     = AppRoot::require(
            array_key_exists('app_root', $overrides) ? (string)$overrides['app_root'] : null
        );
        $srcRoot     = $appRoot . '/src';

        $file = [];
        $path = $appRoot . '/private/intake-config.php';
        if ($overrides === [] && is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                $file = $loaded;
            }
        }

        $pick = static function (string $key, string $env) use ($overrides, $file) {
            if (array_key_exists($key, $overrides)) {
                return $overrides[$key];
            }
            $v = getenv($env);
            if ($v !== false && $v !== '') {
                return $v;
            }
            return $file[$key] ?? null;
        };

        $ipHmacKey = (string)($pick('ip_hmac_key', 'INTAKE_IP_HMAC_KEY') ?? '');
        $encKey    = (string)($pick('enc_key', 'INTAKE_ENC_KEY') ?? '');

        // ★fail closed。鍵が無ければ動かさない
        if (strlen($ipHmacKey) < 32) {
            throw new ConfigException('ip_hmac_key is not configured');
        }
        if (strlen($encKey) < 32) {
            throw new ConfigException('enc_key is not configured');
        }

        $origins = $pick('allowed_origins', 'INTAKE_ALLOWED_ORIGINS');
        if (is_string($origins)) {
            $origins = array_values(array_filter(array_map('trim', explode(',', $origins))));
        }
        if (!is_array($origins) || $origins === []) {
            $origins = ['https://intake.smartlaboworks.com'];
        }
        $requireHttps = $pick('require_https', 'INTAKE_REQUIRE_HTTPS');
        $requireHttps = $requireHttps === null ? true : (bool)$requireHttps;

        // ★データの置き場所。既定は APP_ROOT/private。移す場合も公開領域は許さない
        $privateRoot = self::checkedRoot(
            $pick('private_root', 'INTAKE_PRIVATE_ROOT'),
            $appRoot . '/private',
            'private_root'
        );

        // preflight は「あってもなくてもよい」。設定されたときだけ検査する
        $preflightRoot = self::nonEmptyOrNull($pick('preflight_root', 'INTAKE_PREFLIGHT_ROOT'));
        if ($preflightRoot !== null) {
            $preflightRoot = self::checkedRoot($preflightRoot, $preflightRoot, 'preflight_root');
            // ★正式領域と重ならないこと。ここが混ざると架空データが本番へ入る
            if (PathPolicy::isSame($preflightRoot, $privateRoot)
                || PathPolicy::isInside($privateRoot, $preflightRoot)
                || PathPolicy::isInside($preflightRoot, $privateRoot)) {
                throw new ConfigException('preflight_root overlaps private_root');
            }
        }

        foreach ($origins as $o) {
            if (!is_string($o) || !self::originAcceptable($o, $requireHttps)) {
                throw new ConfigException('allowed_origins must be https');
            }
        }

        return new self(
            dbPath: (string)($pick('db_path', 'INTAKE_DB_PATH') ?? $privateRoot . '/intake.sqlite'),
            ipHmacKey: $ipHmacKey,
            encKey: $encKey,
            allowedOrigins: array_values($origins),
            rateLimitDir: (string)($pick('rate_limit_dir', 'INTAKE_RATELIMIT_DIR') ?? $privateRoot . '/ratelimit'),
            logPath: $pick('log_path', 'INTAKE_LOG_PATH'),
            requireHttps: $requireHttps,
            appRoot: $appRoot,
            srcRoot: $srcRoot,
            privateRoot: $privateRoot,
            adminId: self::nonEmptyOrNull($pick('admin_id', 'INTAKE_ADMIN_ID')),
            adminPasswordHash: self::validAdminHashOrNull($pick('admin_password_hash', 'INTAKE_ADMIN_PASSWORD_HASH')),
            retentionActionsEnabled: self::explicitTrue($pick('retention_actions_enabled', 'INTAKE_RETENTION_ACTIONS_ENABLED')),
            backupPolicyConfirmed: self::explicitTrue($pick('backup_policy_confirmed', 'INTAKE_BACKUP_POLICY_CONFIRMED')),
            backupDir: self::nonEmptyOrNull($pick('backup_dir', 'INTAKE_BACKUP_DIR')),
            preflightRoot: $preflightRoot,
            notificationRecipient: self::checkedAddressOrNull($pick('notification_recipient', 'INTAKE_NOTIFICATION_RECIPIENT')),
            notificationFrom: self::checkedAddressOrNull($pick('notification_from', 'INTAKE_NOTIFICATION_FROM')),
        );
    }

    /**
     * 破壊的操作のフラグを読む。
     *
     * ★`(bool)` へ丸めない。環境変数は必ず文字列で来るため、`"false"` も `"0"` も
     *   `"off"` も PHP では真になりうる。**明示的に真と書いたときだけ真**にする。
     *   迷ったら実行しない側へ倒す（fail closed）。
     */
    private static function explicitTrue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function nonEmptyOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * データ置き場のパスを検査する（SSOT v1.12 §10.11）。
     *
     * ★**まだ存在しなくてよい**（初回起動で作る）。文字列としての規則だけを見る。
     *   実在するなら symlink 逸脱も見る。
     * ★不正なら**既定へ落とさず例外**にする（fail closed）。
     * ★例外の文言へパスを入れない（外へ出るため）。
     */
    private static function checkedRoot(mixed $value, string $fallback, string $label): string
    {
        $raw = self::nonEmptyOrNull($value) ?? $fallback;

        $textual = PathPolicy::checkTextual($raw);
        if ($textual['ok'] !== true) {
            throw new ConfigException($label . ' is not usable: ' . (string)$textual['error']);
        }
        if (is_dir($raw)) {
            $resolved = PathPolicy::checkDir($raw);
            if ($resolved['ok'] !== true) {
                throw new ConfigException($label . ' is not usable: ' . (string)$resolved['error']);
            }

            return (string)$resolved['dir'];
        }

        return PathPolicy::normalize($raw);
    }

    /**
     * 通知メールのアドレスを検査する（SSOT v1.12 §9.11）。
     *
     * ★**1宛先のみ。** 改行・カンマ・セミコロン・山括弧を含む値は
     *   ヘッダー注入の入口になるため、その場で落として null にする
     *   （＝通知しない。fail closed）。例外にしないのは、受付APIまで
     *   止めないためである（管理者資格情報と同じ考え方）。
     */
    private static function checkedAddressOrNull(mixed $value): ?string
    {
        $address = self::nonEmptyOrNull($value);
        if ($address === null) {
            return null;
        }
        // ★ヘッダー注入の入口になる文字を、正規表現に頼らず1文字ずつ見る。
        //   エスケープの取り違えで穴が開くより、読んで分かる形の方が安全である。
        for ($i = 0, $n = strlen($address); $i < $n; ++$i) {
            $code = ord($address[$i]);
            // 制御文字（CR/LF/TAB を含む）・空白・DEL を認めない
            if ($code < 0x21 || $code === 0x7f) {
                return null;
            }
        }
        // 複数宛先・表示名・引用符の入口も認めない
        // ★バックスラッシュは chr(92) で見る。エスケープの取り違えを起こさないため
        if (str_contains($address, chr(92))) {
            return null;
        }
        foreach (self::ADDRESS_FORBIDDEN as $bad) {
            if (str_contains($address, $bad)) {
                return null;
            }
        }
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        if (strlen($address) > 254) {
            return null;
        }

        return $address;
    }

    /**
     * 管理者パスワードの hash を受け取る。
     *
     * ★平文を設定してしまった事故を通さない。
     *   `password_get_info()` が既知のアルゴリズムを返すものだけを受け入れ、
     *   それ以外は **null（＝管理画面を動かさない）** にする。
     *   ここで例外を投げないのは、店舗向けの受付APIまで止めないため。
     */
    private static function validAdminHashOrNull(mixed $value): ?string
    {
        $hash = self::nonEmptyOrNull($value);
        if ($hash === null) {
            return null;
        }
        $info = password_get_info($hash);

        return ($info['algo'] ?? null) ? $hash : null;
    }

    /**
     * 許可オリジンとして受け付けてよいか。
     *
     * ★本番（`require_https` が真＝既定）は **https のみ**。
     *   `require_https` を明示的に偽にしたローカル確認のときに限り、
     *   **自分の端末（127.0.0.1 / ::1 / localhost）の http** だけを許す。
     *   それ以外の http は、ローカル確認中であっても許さない。
     */
    public static function originAcceptable(string $origin, bool $requireHttps): bool
    {
        if (strncmp($origin, 'https://', 8) === 0) {
            return true;
        }
        if ($requireHttps) {
            return false;
        }

        return preg_match('#^http://(127\.0\.0\.1|localhost|\[::1\])(:\d{1,5})?$#', $origin) === 1;
    }
}
