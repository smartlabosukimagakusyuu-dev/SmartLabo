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
    ];

    public function __construct(
        public readonly string $dbPath,
        public readonly string $ipHmacKey,
        public readonly string $encKey,
        public readonly array $allowedOrigins,
        public readonly string $rateLimitDir,
        public readonly ?string $logPath,
        public readonly bool $requireHttps,
    ) {
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
        );
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
