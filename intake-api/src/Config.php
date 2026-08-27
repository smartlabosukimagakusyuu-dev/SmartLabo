<?php
/**
 * HP Intake API — 設定。
 *
 * SSOT: docs/website/HP_ONBOARDING_INTAKE_DATA_MODEL_V1.md v1.2
 *
 * ★秘密値（IP HMAC鍵・暗号鍵）は環境変数、または public_html の外に置く
 *   private/intake-config.php からのみ読む。**未設定なら起動しない（fail closed）**。
 * ★このファイルに既定の鍵を書かない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake;

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
         * 本番バックアップの世代・削除方針が確定済みか（SSOT v1.7 §9.8-2）。
         * ★既定は **false**。4G でバックアップ方針を確定するまで削除を通さない。
         *   古いバックアップから消したはずの回答が復活する運用を作らないため。
         */
        public readonly bool $backupPolicyConfirmed = false,
    ) {
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
     * 環境変数 → private/intake-config.php の順で読む。
     * @param array<string,mixed> $overrides テスト専用の直接指定
     */
    public static function load(array $overrides = []): self
    {
        $file = [];
        $path = __DIR__ . '/../private/intake-config.php';
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

        foreach ($origins as $o) {
            if (!is_string($o) || !self::originAcceptable($o, $requireHttps)) {
                throw new ConfigException('allowed_origins must be https');
            }
        }

        return new self(
            dbPath: (string)($pick('db_path', 'INTAKE_DB_PATH') ?? __DIR__ . '/../private/intake.sqlite'),
            ipHmacKey: $ipHmacKey,
            encKey: $encKey,
            allowedOrigins: array_values($origins),
            rateLimitDir: (string)($pick('rate_limit_dir', 'INTAKE_RATELIMIT_DIR') ?? __DIR__ . '/../private/ratelimit'),
            logPath: $pick('log_path', 'INTAKE_LOG_PATH'),
            requireHttps: $requireHttps,
            adminId: self::nonEmptyOrNull($pick('admin_id', 'INTAKE_ADMIN_ID')),
            adminPasswordHash: self::validAdminHashOrNull($pick('admin_password_hash', 'INTAKE_ADMIN_PASSWORD_HASH')),
            retentionActionsEnabled: self::explicitTrue($pick('retention_actions_enabled', 'INTAKE_RETENTION_ACTIONS_ENABLED')),
            backupPolicyConfirmed: self::explicitTrue($pick('backup_policy_confirmed', 'INTAKE_BACKUP_POLICY_CONFIRMED')),
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
