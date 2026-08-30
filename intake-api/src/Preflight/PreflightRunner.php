<?php
/**
 * HP Intake API — preflight 通し確認の本体
 * （HP-ONBOARDING-4H-4 / SSOT §9.10）。
 *
 * ── 何をするか ──
 * 隔離領域の設定だけで Kernel を組み、**本物の App / AdminApp / Guard /
 * RateLimiter / TokenService / AnswerService** を通して、
 * 案件作成から確定までを架空データで1周する。
 * テスト専用の偽物で迂回しない（本番配置物と同じコードを通す）。
 *
 * ── 守ること ──
 * ★正式 `intake-config.php` を **require しない**（overrides で組む）。
 * ★正式DB・正式ログ・正式 ratelimit へ**1バイトも書かない**
 *   （`PreflightBuilder::assertInsideRoot()` が事前に表明検査する）。
 * ★通知は `NullNotifier` を**明示注入**する。実メールの経路を持たない。
 * ★管理者情報はこのプロセス内で作り、**設定ファイルへ書かない**。
 *   ID・平文・hash のいずれも**出力しない**。
 * ★`retention_actions_enabled` / `backup_policy_confirmed` は false。
 * ★架空データのみ（`PreflightFixture`）。実顧客情報・実 token を使わない。
 * ★戻り値へ絶対パス・秘密値を載せない。段の名前と成否だけを返す。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Preflight;

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Http\Response;
use SmartLabo\Intake\Kernel;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Notify\NullNotifier;

final class PreflightRunner
{
    /** 通し確認で使う Origin。★http は 127.0.0.1 のみ許される */
    public const ORIGIN = 'http://127.0.0.1';

    /** 架空の管理者IDに付ける接頭辞（★これ以外は出力しない） */
    public const ADMIN_ID_PREFIX = 'preflight-';

    /** @var list<array{ok:bool,label:string}> */
    private array $steps = [];

    public function __construct(private readonly PreflightBuilder $builder)
    {
    }

    /**
     * 通し確認を実行する。
     *
     * @return array{ok:bool,steps:list<array{ok:bool,label:string}>,passed:int,failed:int,error?:string}
     */
    public function run(): array
    {
        $overrides = $this->builder->configOverrides();

        // ★解決後の全パスが隔離領域の内側であることを、動かす前に確かめる
        $inside = $this->builder->assertInsideRoot($overrides);
        if ($inside['ok'] !== true) {
            return $this->fail('path_outside_preflight');
        }
        if ((string)$overrides['ip_hmac_key'] === '' || (string)$overrides['enc_key'] === '') {
            return $this->fail('preflight_keys_missing');
        }
        if ((string)$overrides['ip_hmac_key'] === (string)$overrides['enc_key']) {
            return $this->fail('preflight_keys_identical');
        }

        // ★Argon2id が無ければ止める（BCRYPT へ落とさない）
        if (!defined('PASSWORD_ARGON2ID')) {
            return $this->fail('argon2id_unavailable');
        }

        // ★管理者情報はここで作り、ここでしか使わない。出力しない
        $adminId = self::ADMIN_ID_PREFIX . bin2hex(random_bytes(4));
        $adminPw = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');

        $overrides['admin_id']            = $adminId;
        $overrides['admin_password_hash'] = password_hash($adminPw, PASSWORD_ARGON2ID);

        try {
            $config = Config::load($overrides);
        } catch (\Throwable $e) {
            return $this->fail('config_load_failed');
        }

        // ★通知は NullNotifier を明示注入する（設定からの組み立てへ落ちない）
        $kernel = new Kernel($config, null, new NullNotifier());

        try {
            $this->scenario($kernel, $adminId, $adminPw);
        } catch (\Throwable $e) {
            // ★例外の本文を外へ出さない（パス・値が載りうるため）
            $this->step('通し確認が例外で中断した（' . $e::class . '）', false);
        }
        unset($adminPw, $overrides);

        $failed = count(array_filter($this->steps, static fn (array $s): bool => $s['ok'] !== true));

        return [
            'ok'     => $failed === 0,
            'steps'  => $this->steps,
            'passed' => count($this->steps) - $failed,
            'failed' => $failed,
        ];
    }

    /* ============================================================ シナリオ */

    private function scenario(Kernel $k, string $adminId, string $adminPw): void
    {
        /* -------------------------------------------------- 準備 */

        $this->step(
            'DBスキーマ版が ' . Migrator::SCHEMA_VERSION . ' で、必須8表が揃っている',
            (int)$k->db->pdo()->query('PRAGMA user_version')->fetchColumn() === Migrator::SCHEMA_VERSION
            && $this->tableCount($k) === 8
        );
        $this->step(
            '通知は無効（実メールの経路を持たない）',
            $k->notifier instanceof NullNotifier && $k->notifier->enabled() === false
        );
        $this->step(
            '保持削除とバックアップ方針のフラグが false のまま',
            $k->config->retentionActionsEnabled === false
            && $k->config->backupPolicyConfirmed === false
        );

        /* -------------------------------------------------- A 管理ログイン */

        $bad = $k->app->handle($this->adminPost('/admin/login', [
            'admin_id' => $adminId, 'password' => 'wrong-password',
        ]));
        $this->step('誤ったパスワードでログインできない', $bad->status === 401);

        $login = $k->app->handle($this->adminPost('/admin/login', [
            'admin_id' => $adminId, 'password' => $adminPw,
        ]));
        $secret = (string)($login->cookies[0]['value'] ?? '');
        $admin  = [Config::ADMIN_COOKIE_NAME => $secret];
        $this->step('管理ログインが成功し、session Cookie が発行される',
            $login->status === 303 && strlen($secret) === Config::SECRET_CHARS);
        $this->step('DB には session の hash だけが入る（平文を保存しない）',
            $this->count($k, 'SELECT COUNT(*) FROM intake_admin_sessions WHERE session_hash = :h',
                [':h' => hash('sha256', $secret)]) === 1);

        $listA = $k->app->handle($this->adminGet('/admin/', $admin));
        $listB = $k->app->handle($this->adminGet('/admin/', $admin));
        $csrfA = $this->csrfOf($listA->rawBody);
        $this->step('認証後の画面は開くたびに CSRF が新しくなる',
            $csrfA !== '' && $csrfA !== $this->csrfOf($listB->rawBody));

        /* -------------------------------------------------- B 案件作成 */

        $newForm = $k->app->handle($this->adminGet('/admin/new', $admin));
        $created = $k->app->handle($this->adminPost('/admin/create', [
            'csrf_token'         => $this->csrfOf($newForm->rawBody),
            'shop_display_name'  => PreflightFixture::SHOP,
            'contract_type'      => 'salon',
            'drive_url'          => PreflightFixture::DRIVE_URL,
            'drive_shared_email' => PreflightFixture::DRIVE_EMAIL,
        ], $admin));

        preg_match('/(HP-\d{6}-\d{4})/', (string)$created->rawBody, $m);
        $number = (string)($m[1] ?? '');
        $case   = $number === '' ? null : $k->cases->findByNumber($number);
        $caseId = (int)($case['id'] ?? 0);
        $this->step('案件番号をサーバーが採番する（店舗名を含まない）',
            preg_match('/^HP-\d{6}-\d{4}$/', $number) === 1
            && !str_contains($number, PreflightFixture::SHOP));
        $this->step('初期状態は draft で、回答行が初期化される',
            $caseId > 0 && (string)($case['status'] ?? '') === 'draft'
            && $this->count($k, 'SELECT COUNT(*) FROM intake_answers WHERE intake_case_id = :i',
                [':i' => $caseId]) === 1);

        preg_match('#/start\#([A-Za-z0-9_-]{43})#', (string)$created->rawBody, $tm);
        $token = (string)($tm[1] ?? '');
        $this->step('ご案内リンクの平文は作成直後に1回だけ出る',
            strlen($token) === Config::SECRET_CHARS
            && substr_count((string)$created->rawBody, $token) === 1);
        $this->step('DB には token の hash だけが入る',
            $this->count($k, 'SELECT COUNT(*) FROM intake_tokens WHERE token_hash = :h',
                [':h' => hash('sha256', $token)]) === 1);
        $this->step('Drive の URL と共有先メールは暗号文で保存される',
            $k->cases->driveFolderUrl($caseId) === PreflightFixture::DRIVE_URL
            && !str_contains((string)($k->cases->find($caseId)['drive_folder_url_enc'] ?? ''), 'drive.google.com'));

        /* -------------------------------------------------- C 店舗の交換 */

        $start = $k->app->handle($this->json('POST', '/session/start', ['token' => $token]));
        $sid   = (string)($start->cookies[0]['value'] ?? '');
        $shop  = [Config::COOKIE_NAME => $sid];
        $this->step('token を1度だけ交換して店舗 session を発行する',
            $start->status === 200 && strlen($sid) === Config::SECRET_CHARS);
        $this->step('応答本文に token も session の平文も出さない',
            !str_contains((string)$start->rawBody, $token)
            && !str_contains((string)$start->rawBody, $sid));

        $bogus = $k->app->handle($this->json('POST', '/session/start', ['token' => str_repeat('z', 43)]));
        $this->step('存在しない token は 404 の固定応答で返す', $bogus->status === 404);

        /* -------------------------------------------------- D 入力と保存 */

        $caseRes = $k->app->handle($this->shopGet('/case', $shop));
        $this->step('認証済みの本人だけが案件を取得できる', $caseRes->status === 200);

        $sections = PreflightFixture::answers();
        $version  = 1;
        $saveOk   = true;
        foreach (Migrator::ANSWER_SECTIONS as $name) {
            $res = $k->app->handle($this->json('POST', '/answers/save', [
                'version'  => $version,
                'sections' => [$name => $sections[$name]],
            ], $shop));
            if ($res->status !== 200 || (int)$res->body['version'] !== $version + 1) {
                $saveOk = false;
                break;
            }
            $version = (int)$res->body['version'];
        }
        $this->step('11分類の架空回答を1つずつ保存でき、version が毎回1つ進む',
            $saveOk && $version === count(Migrator::ANSWER_SECTIONS) + 1);

        $stale = $k->app->handle($this->json('POST', '/answers/save', [
            'version' => 1, 'sections' => ['basic' => ['legal_name' => '上書きされてはいけない']],
        ], $shop));
        $this->step('古い version の保存は 409 で拒否する', $stale->status === 409);

        $unknown = $k->app->handle($this->json('POST', '/answers/save', [
            'version'  => $version,
            'sections' => ['basic' => ['not_a_real_key' => 'x']],
        ], $shop));
        $this->step('未知キーは要求ごと 400 で拒否する', $unknown->status === 400);

        /* -------------------------------------------------- E 素材と提出 */

        $confirm = $k->app->handle($this->json('POST', '/drive/confirm', ['confirmed' => true], $shop));
        $this->step('素材アップロードの申告を受け付ける（冪等）', $confirm->status === 200);

        $submissionId = $this->uuid4();
        $submit = $k->app->handle($this->json('POST', '/submit',
            ['submission_id' => $submissionId], $shop));
        $this->step('最終提出が成功する',
            $submit->status === 200 && ($submit->body['ok'] ?? false) === true);

        $again = $k->app->handle($this->json('POST', '/submit',
            ['submission_id' => $submissionId], $shop));
        $this->step('同じ submission_id の再送は同じ結果を返し、履歴を増やさない',
            $again->status === 200
            && $this->count($k, 'SELECT COUNT(*) FROM intake_submission_history WHERE intake_case_id = :i',
                [':i' => $caseId]) === 1);

        $other = $k->app->handle($this->json('POST', '/submit',
            ['submission_id' => $this->uuid4()], $shop));
        $this->step('別の submission_id での二重提出は 409 で拒否する', $other->status === 409);

        $this->step('提出通知は送られない（NullNotifier のため実メール0通）',
            $k->notifier->enabled() === false);

        /* -------------------------------------------------- F 管理の確認 */

        $detail = $k->app->handle($this->adminGet('/admin/case', $admin, ['case' => $number]));
        $this->step('管理画面で案件詳細を開ける', $detail->status === 200);

        $k->answers->saveAdminSettings($caseId, [
            'web_links' => ['salon_booking_url' => ''],
            'privacy'   => [
                'destination'       => '架空の送信先',
                'storage'           => '架空の保管方法',
                'external_services' => [],
                'consent_checkbox'  => true,
            ],
        ]);
        $status = $k->app->handle($this->adminPost('/admin/status', [
            'csrf_token' => $this->csrfOf($detail->rawBody),
            'case'       => $number,
            'to'         => 'reviewed',
        ], $admin));
        $this->step('reviewed へ状態を変更できる', $status->status === 303);

        $export = $k->app->handle($this->adminGet('/admin/export', $admin, ['case' => $number]));
        $exportBody = (string)$export->rawBody;
        $this->step('検証済み JSON を書き出せる', $export->status === 200 && $exportBody !== '');
        $this->step('書き出しに token・session・Drive URL・submission_id を含めない',
            !str_contains($exportBody, $token) && !str_contains($exportBody, $sid)
            && !str_contains($exportBody, PreflightFixture::DRIVE_URL)
            && !str_contains($exportBody, $submissionId));

        /* -------------------------------------------------- G 確定と保持 */

        $lockForm = $k->app->handle($this->adminGet('/admin/lock', $admin, ['case' => $number]));
        $lock = $k->app->handle($this->adminPost('/admin/lock/send', [
            'csrf_token' => $this->csrfOf($lockForm->rawBody),
            'case'         => $number,
            'confirm_case' => $number,
        ], $admin));
        $after = $k->cases->findByNumber($number);
        $this->step('locked へ確定でき、店舗 session と token が失効する',
            $lock->status === 303 && (string)($after['status'] ?? '') === 'locked'
            && $k->tokens->activeCount($caseId) === 0);

        $afterLock = $k->app->handle($this->shopGet('/case', $shop));
        $this->step('確定後は店舗の session で案件を取得できない', $afterLock->status === 404);

        $retention = $k->app->handle($this->adminGet('/admin/retention', $admin));
        $this->step('保持期限の一覧を開ける', $retention->status === 200);
        $this->step('フラグが false のため保持削除は実行できない',
            $k->config->retentionActionsEnabled === false);
    }

    /* ================================================================ 補助 */

    /** @param array<string,mixed> $body */
    private function json(string $method, string $path, array $body, array $cookies = []): Request
    {
        return new Request(
            method: $method,
            path: $path,
            headers: ['Content-Type' => 'application/json', 'Origin' => self::ORIGIN],
            body: (string)json_encode($body),
            cookies: $cookies,
            isHttps: false,
            clientIp: '127.0.0.1',
        );
    }

    private function shopGet(string $path, array $cookies = []): Request
    {
        return new Request(
            method: 'GET',
            path: $path,
            headers: ['Origin' => self::ORIGIN, 'Sec-Fetch-Site' => 'same-origin', 'Sec-Fetch-Mode' => 'cors'],
            body: '',
            cookies: $cookies,
            isHttps: false,
            clientIp: '127.0.0.1',
        );
    }

    /** @param array<string,string> $query */
    private function adminGet(string $path, array $cookies = [], array $query = []): Request
    {
        return new Request(
            method: 'GET',
            path: $path,
            headers: ['Origin' => self::ORIGIN, 'Sec-Fetch-Site' => 'same-origin'],
            body: '',
            cookies: $cookies,
            isHttps: false,
            clientIp: '127.0.0.1',
            query: $query,
        );
    }

    /** @param array<string,string> $fields */
    private function adminPost(string $path, array $fields, array $cookies = []): Request
    {
        return new Request(
            method: 'POST',
            path: $path,
            headers: ['Content-Type' => 'application/x-www-form-urlencoded', 'Origin' => self::ORIGIN],
            body: http_build_query($fields),
            cookies: $cookies,
            isHttps: false,
            clientIp: '127.0.0.1',
        );
    }

    private function csrfOf(?string $html): string
    {
        preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', (string)$html, $m);

        return (string)($m[1] ?? '');
    }

    private function uuid4(): string
    {
        $b    = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** @param array<string,mixed> $params */
    private function count(Kernel $k, string $sql, array $params = []): int
    {
        $stmt = $k->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    private function tableCount(Kernel $k): int
    {
        return (int)$k->db->pdo()
            ->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE 'intake_%'")
            ->fetchColumn();
    }

    private function step(string $label, bool $ok): void
    {
        $this->steps[] = ['ok' => $ok, 'label' => $label];
    }

    /**
     * @return array{ok:bool,steps:list<array{ok:bool,label:string}>,passed:int,failed:int,error:string}
     */
    private function fail(string $error): array
    {
        return ['ok' => false, 'steps' => $this->steps, 'passed' => 0, 'failed' => 1, 'error' => $error];
    }
}
