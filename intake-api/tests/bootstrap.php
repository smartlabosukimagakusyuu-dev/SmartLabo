<?php
/**
 * HP Intake API — テスト基盤（外部ライブラリを使わない。Composer も導入しない）
 *
 * 実行:
 *   php -c intake-api/dev/php.ini intake-api/tests/run-tests.php
 *
 * ★テストDBは intake-api/tests/.tmp/ 配下のみ。本番・既存DBへは一切接続しない。
 * ★鍵は固定のダミーのみ（実鍵をテストへ持ち込まない）。
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/Autoload.php';

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Notify\Notifier;
use SmartLabo\Intake\Notify\SubmissionNotice;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Kernel;
use SmartLabo\Intake\Support\Clock;

/** テスト専用の固定ダミー鍵（本番の鍵ではない） */
const TEST_IP_HMAC_KEY = 'test-only-ip-hmac-key-0123456789abcdef';
const TEST_ENC_KEY     = 'test-only-enc-key-0123456789abcdefghij';
const TEST_ORIGIN      = 'https://intake.smartlaboworks.com';

/** 時刻を自由に動かせる Clock（期限テスト用） */
final class TestClock extends Clock
{
    public int $offset = 0;

    public function now(): int
    {
        return time() + $this->offset;
    }

    public function advance(int $seconds): void
    {
        $this->offset += $seconds;
    }
}

/**
 * テスト用の通知（4H-R0）。
 * ★**実メールを1通も送らない。** 送られた内容を控えるだけである。
 *   `mail()` を呼ばないので、静的検査の allowlist にも触れない。
 */
final class FakeNotifier implements Notifier
{
    /** @var list<array{case_number:string,occurred_at:string,subject:string,body:string}> */
    public array $sent = [];

    public bool $enabledFlag = true;

    /** 送信に失敗する状況を作るための切り替え */
    public bool $failNext = false;

    public function enabled(): bool
    {
        return $this->enabledFlag;
    }

    public function notifySubmitted(SubmissionNotice $notice): bool
    {
        if ($this->failNext) {
            return false;
        }
        $this->sent[] = [
            'case_number' => $notice->caseNumber,
            'occurred_at' => $notice->occurredAt,
            'subject'     => $notice->subject(),
            'body'        => $notice->body(),
        ];

        return true;
    }

    public function count(): int
    {
        return count($this->sent);
    }

    /** 送った全文（件名＋本文）をつないで返す。混入検査に使う */
    public function dump(): string
    {
        $out = '';
        foreach ($this->sent as $item) {
            $out .= $item['subject'] . "\n" . $item['body'] . "\n";
        }

        return $out;
    }
}

final class TestRunner
{
    /** @var list<array{name:string,fn:callable}> */
    public static array $tests = [];
    public static int $passed = 0;
    /** @var list<string> */
    public static array $failed = [];
}

function test(string $name, callable $fn): void
{
    TestRunner::$tests[] = ['name' => $name, 'fn' => $fn];
}

function assertTrue(bool $cond, string $message = ''): void
{
    if (!$cond) {
        throw new RuntimeException($message !== '' ? $message : 'assertTrue failed');
    }
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s (expected=%s actual=%s)',
            $message !== '' ? $message : 'assertSame failed',
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function tmpDir(): string
{
    $dir = __DIR__ . '/.tmp';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    return $dir;
}

/** 使い捨てのテスト環境を作る（DB・ratelimit・ログをテストごとに分離） */
function makeKernel(?TestClock $clock = null, array $overrides = [], ?Notifier $notifier = null): Kernel
{
    static $seq = 0;
    ++$seq;
    $base = tmpDir() . '/case-' . getmypid() . '-' . $seq;
    if (!is_dir($base)) {
        mkdir($base, 0700, true);
    }

    $config = Config::load(array_merge([
        'db_path'         => $base . '/intake.sqlite',
        'ip_hmac_key'     => TEST_IP_HMAC_KEY,
        'enc_key'         => TEST_ENC_KEY,
        'allowed_origins' => [TEST_ORIGIN],
        'rate_limit_dir'  => $base . '/ratelimit',
        'log_path'        => $base . '/intake.log',
        'require_https'   => true,
    ], $overrides));

    return new Kernel($config, $clock ?? new TestClock(), $notifier);
}

/** JSON POST リクエストを組み立てる */
function jsonPost(string $path, array $body, array $opts = []): Request
{
    $headers = [
        'Content-Type' => $opts['content_type'] ?? 'application/json',
        'Origin'       => $opts['origin'] ?? TEST_ORIGIN,
    ];
    if (($opts['no_origin'] ?? false) === true) {
        unset($headers['Origin']);
    }

    return new Request(
        method: $opts['method'] ?? 'POST',
        path: $path,
        headers: $headers,
        body: $opts['raw_body'] ?? (string)json_encode($body),
        cookies: $opts['cookies'] ?? [],
        isHttps: $opts['https'] ?? true,
        clientIp: $opts['ip'] ?? '203.0.113.10',
    );
}

function jsonGet(string $path, array $opts = []): Request
{
    $headers = ['Origin' => $opts['origin'] ?? TEST_ORIGIN];
    if (($opts['no_origin'] ?? false) === true) {
        unset($headers['Origin']);
    }

    return new Request(
        method: $opts['method'] ?? 'GET',
        path: $path,
        headers: $headers,
        body: '',
        cookies: $opts['cookies'] ?? [],
        isHttps: $opts['https'] ?? true,
        clientIp: $opts['ip'] ?? '203.0.113.10',
    );
}

/**
 * 提出要求の冪等化キー（SSOT v1.3 §6.4）。UUID v4 を1件つくる。
 * ★4C の「送信を押すたびに新しい値を生成する」契約をテストでも同じ形で守る。
 */
function newSubmissionId(): string
{
    $b     = random_bytes(16);
    $b[6]  = chr((ord($b[6]) & 0x0f) | 0x40); // version 4
    $b[8]  = chr((ord($b[8]) & 0x3f) | 0x80); // variant 10xx

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/**
 * Smart Labo の制作設定（SSOT v1.9 §3.12）を埋める。
 *
 * ★4F-R3 から、書き出しは店舗の提出条件に加えて**この5件**も要求する。
 *   店舗の提出そのものは、これが無くても通る。
 */
function setAdminSettings(object $k, int $caseId): void
{
    $k->answers->saveAdminSettings($caseId, [
        // ★空欄は**空文字**で保存する（4F-R4）。null は保存できない
        'web_links' => ['salon_booking_url' => ''],
        'privacy'   => [
            'destination'       => '架空の送信先',
            'storage'           => '架空の保管方法',
            'external_services' => [],
            'consent_checkbox'  => true,
        ],
    ]);
}

/** 提出条件を満たす完全な回答（架空データのみ） */
function completeSections(): array
{
    $images = [];
    for ($i = 0; $i < 8; ++$i) {
        $images[] = [
            'file_name'        => 'photo-' . $i . '.jpg',
            'role'             => $i === 0 ? 'exterior' : 'interior',
            'provider'         => 'shop',
            'rights_confirmed' => true,
            'published'        => true,
            'ai_generated'     => false,
        ];
    }

    $confirmations = [];
    for ($i = 1; $i <= 13; ++$i) {
        $confirmations[] = [
            'code'      => sprintf('L-%02d', $i),
            'agreed'    => true,
            'agreed_at' => '2026-08-27T00:00:00Z',
        ];
    }

    $weekly = [];
    for ($d = 0; $d < 7; ++$d) {
        $weekly[] = ['day' => $d, 'closed' => $d === 1, 'open' => $d === 1 ? null : '09:00', 'close' => $d === 1 ? null : '18:00'];
    }

    return [
        'basic' => [
            'legal_name'       => 'ヘアサロン ハルカゼ',
            'operator_name'    => 'ハルカゼ',
            'postal_code'      => '000-0000',
            'address'          => '架空県架空市架空町1-2-3',
            'access_text'      => '架空駅から徒歩3分',
            'description'      => '架空の店舗紹介文です。',
            'payment_methods'  => ['cash'],
            'booking_methods'  => ['web'],
            'internal_contact' => ['phone' => '03-0000-0000', 'email' => 'internal@example.invalid'],
            // ★4F-R3: SSOT §3 が必須と定める項目をすべて満たす
            'address_visibility' => 'full',
            'parking'            => ['type' => 'own', 'note' => '2台'],
        ],
        'business_hours' => [
            'weekly' => $weekly, 'closed_note' => '毎週月曜', 'irregular_notice' => 'none',
        ],
        'menus'          => [[
            'name' => 'カット', 'price_type' => 'fixed', 'price_inc_tax' => 5500,
            'tax_type' => 'inc', 'published' => true, 'bookable' => true,
            'first_time_only' => false, 'limited_period' => false,
        ]],
        'staff'      => [],
        'promotion'  => [
            'strengths'         => ['架空の強み'],
            'customer_profile'  => '架空の顧客層',
            'problems'          => '架空のお悩み',
            'recommended_menus' => ['カット'],
            'concept'           => '架空のコンセプト',
            'exclusions'        => 'なし',
            'forbidden_expressions' => 'なし',
        ],
        'design'         => [
            'template' => 'beauty', 'tone' => ['シンプル'], 'hero_message' => '架空のメッセージ',
            'logo' => 'none', 'emphasis' => 'photo',
        ],
        'web_links'      => ['contact_methods' => ['phone'], 'map_display' => 'show'],
        'contact_form'   => ['enabled' => false],
        'privacy'        => [
            'collected_data' => ['name'], 'purpose' => '架空の目的',
            'retention' => '1年', 'third_party' => 'none',
            'contact_window' => '架空の窓口', 'marketing_use' => 'no',
        ],
        'image_metadata' => $images,
        'rights'         => ['confirmations' => $confirmations, 'agreed_by' => '架空 担当者'],
    ];
}
