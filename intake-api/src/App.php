<?php
/**
 * HP Intake API — ルーティングと endpoint 実装。
 *
 * SSOT: docs/website/HP_ONBOARDING_INTAKE_DATA_MODEL_V1.md v1.2
 *
 * endpoint（4B の範囲）:
 *   POST /session/start   token → session Cookie の初回交換（SSOT §4.5-A）
 *   GET  /case            認証済み案件の取得（最小）
 *   POST /answers/save    途中保存（楽観ロック）
 *   POST /submit          最終提出
 *   POST /session/logout  session の個別失効
 *
 * ★店舗入力画面は作らない（4C の範囲）。
 * ★token / session secret を応答本文へ再掲しない。
 * ★CORS を許可しない。
 */
declare(strict_types=1);

namespace SmartLabo\Intake;

use SmartLabo\Intake\Http\Guard;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Http\Response;
use SmartLabo\Intake\Service\AnswerService;
use SmartLabo\Intake\Service\Audit;
use SmartLabo\Intake\Service\CaseService;
use SmartLabo\Intake\Service\RateLimiter;
use SmartLabo\Intake\Service\SessionService;
use SmartLabo\Intake\Service\TokenService;
use SmartLabo\Intake\Support\Clock;
use SmartLabo\Intake\Support\Logger;

final class App
{
    public function __construct(
        private readonly Config $config,
        private readonly Guard $guard,
        private readonly RateLimiter $rateLimiter,
        private readonly TokenService $tokens,
        private readonly SessionService $sessions,
        private readonly CaseService $cases,
        private readonly AnswerService $answers,
        private readonly Audit $audit,
        private readonly Logger $logger,
        private readonly Clock $clock,
    ) {
    }

    public function handle(Request $req): Response
    {
        try {
            return match ($req->path) {
                '/session/start'  => $this->sessionStart($req),
                '/session/logout' => $this->sessionLogout($req),
                '/case'           => $this->getCase($req),
                '/answers/save'   => $this->saveAnswers($req),
                '/submit'         => $this->submit($req),
                default           => Response::unavailable(),
            };
        } catch (\Throwable $e) {
            // 例外の内容を外部へ出さない（SSOT §10.6）
            $this->logger->error('unhandled_error', ['http_status' => 500]);

            return Response::error('server_error', 500);
        }
    }

    /**
     * POST /session/start
     * SSOT §5 の15手順の順序で判定する。
     */
    private function sessionStart(Request $req): Response
    {
        [$fail, $payload] = $this->guard->checkJsonPost($req); // 1-6
        if ($fail !== null) {
            return $fail;
        }

        $ipHmac = $this->rateLimiter->ipHmac($req->clientIp);

        // 7. token 形式
        $token = $payload['token'] ?? null;
        if (!is_string($token) || !Support\Secret::isWellFormed($token)) {
            $this->rateLimiter->allow('token_start', $ipHmac);
            $this->audit->record(null, 'token_rejected', 'invalid', $ipHmac);
            $this->logger->warn('token_rejected', ['result_code' => 'invalid', 'ip_hmac' => $ipHmac, 'http_status' => 404]);

            return Response::unavailable();
        }

        // 8. rate limit（無効token試行: 10分5回）
        if (!$this->rateLimiter->allow('token_start', $ipHmac)) {
            $this->audit->record(null, 'rate_limited', 'rate_limited', $ipHmac);
            $this->logger->warn('rate_limited', ['bucket' => 'token_start', 'ip_hmac' => $ipHmac, 'http_status' => 429]);

            return Response::error('rate_limited', 429);
        }

        // 9-11. hash 照合 / revoked / expires
        $verified = $this->tokens->verify($token);
        if ($verified['row'] === null) {
            $this->audit->record(null, 'token_rejected', $verified['reason'], $ipHmac);
            $this->logger->warn('token_rejected', ['result_code' => $verified['reason'], 'ip_hmac' => $ipHmac, 'http_status' => 404]);

            return Response::unavailable();
        }
        $tokenRow = $verified['row'];
        $caseId   = (int)$tokenRow['intake_case_id'];

        // 12. 案件 status
        $case = $this->cases->find($caseId);
        if ($case === null || !in_array((string)$case['status'], CaseService::READABLE, true)) {
            $this->audit->record($caseId, 'token_rejected', 'status', $ipHmac);
            $this->logger->warn('token_rejected', ['result_code' => 'status', 'ip_hmac' => $ipHmac, 'http_status' => 404]);

            return Response::unavailable();
        }

        // 13-14. session 発行 → Cookie
        $this->tokens->touch((int)$tokenRow['id']);
        $secret = $this->sessions->start($caseId, (int)$tokenRow['id']);

        $this->audit->record($caseId, 'token_accepted', 'ok', $ipHmac);
        $this->logger->info('token_accepted', [
            'case_number' => (string)$case['case_number'],
            'result_code' => 'ok',
            'ip_hmac'     => $ipHmac,
            'http_status' => 200,
        ]);

        // 15. 固定形式の応答。★token / session secret を本文へ入れない
        return Response::ok([
            'case_number' => (string)$case['case_number'],
            'status'      => (string)$case['status'],
            'editable'    => in_array((string)$case['status'], CaseService::EDITABLE, true),
        ])->withCookie(Config::COOKIE_NAME, $secret, Config::SESSION_IDLE_TTL);
    }

    private function sessionLogout(Request $req): Response
    {
        [$fail] = $this->guard->checkJsonPost($req);
        if ($fail !== null) {
            return $fail;
        }
        $auth = $this->authenticate($req);
        if ($auth === null) {
            return Response::unavailable();
        }
        $this->sessions->revoke((int)$auth['session']['id'], (int)$auth['case']['id']);

        return Response::ok(['logged_out' => true])->withClearedCookie(Config::COOKIE_NAME);
    }

    private function getCase(Request $req): Response
    {
        $fail = $this->guard->checkGet($req);
        if ($fail !== null) {
            return $fail;
        }
        $auth = $this->authenticate($req);
        if ($auth === null) {
            return Response::unavailable();
        }
        $case    = $auth['case'];
        $answers = $this->answers->get((int)$case['id']);

        return Response::ok([
            'case_number'     => (string)$case['case_number'],
            'contract_type'   => (string)$case['contract_type'],
            'status'          => (string)$case['status'],
            'editable'        => in_array((string)$case['status'], CaseService::EDITABLE, true),
            'current_step'    => $case['current_step'],
            'drive_confirmed' => $case['drive_upload_confirmed_at'] !== null,
            'version'         => $answers['version'],
            'schema_version'  => $answers['schema_version'],
            'sections'        => $answers['sections'],
        ]);
    }

    private function saveAnswers(Request $req): Response
    {
        [$fail, $payload] = $this->guard->checkJsonPost($req);
        if ($fail !== null) {
            return $fail;
        }
        $auth = $this->authenticate($req);
        if ($auth === null) {
            return Response::unavailable();
        }
        $case   = $auth['case'];
        $caseId = (int)$case['id'];
        $ipHmac = $this->rateLimiter->ipHmac($req->clientIp);

        // rate limit: session ＋ HMAC化IP 単位で 10分60回
        $identity = $ipHmac . ':' . substr((string)$auth['session']['session_hash'], 0, 16);
        if (!$this->rateLimiter->allow('answer_save', $identity)) {
            $this->audit->record($caseId, 'rate_limited', 'rate_limited', $ipHmac);

            return Response::error('rate_limited', 429);
        }

        if (!in_array((string)$case['status'], CaseService::EDITABLE, true)) {
            return Response::error('not_editable', 409);
        }

        $sections = $payload['sections'] ?? null;
        $version  = $payload['version'] ?? null;
        if (!is_array($sections) || !is_int($version)) {
            return Response::error('bad_request', 400);
        }

        $result = $this->answers->save($caseId, $sections, $version);
        if ($result['ok'] !== true) {
            $status = match ($result['error']) {
                'conflict'          => 409,
                'payload_too_large' => 413,
                default             => 400,
            };

            return Response::error((string)$result['error'], $status);
        }

        $this->logger->info('answer_saved', [
            'case_number' => (string)$case['case_number'],
            'result_code' => 'ok',
            'ip_hmac'     => $ipHmac,
            'http_status' => 200,
        ]);

        return Response::ok(['version' => $result['version']]);
    }

    /**
     * POST /submit
     * body: { "submission_id": "<UUID v4>" }
     *
     * submission_id は**提出要求の冪等化キー**である（SSOT v1.3 §6.4）。
     * ★値をログ・監査 result_code・エラー本文へ出さない（SSOT §10.7）。
     */
    private function submit(Request $req): Response
    {
        [$fail, $payload] = $this->guard->checkJsonPost($req);
        if ($fail !== null) {
            return $fail;
        }
        $auth = $this->authenticate($req);
        if ($auth === null) {
            return Response::unavailable();
        }
        $case   = $auth['case'];
        $caseId = (int)$case['id'];
        $ipHmac = $this->rateLimiter->ipHmac($req->clientIp);

        $identity = $ipHmac . ':' . substr((string)$auth['session']['session_hash'], 0, 16);
        if (!$this->rateLimiter->allow('submit', $identity)) {
            $this->audit->record($caseId, 'rate_limited', 'rate_limited', $ipHmac);

            return Response::error('rate_limited', 429);
        }

        // 冪等化キーは必須。形式は UUID v4 のみ（SSOT v1.3 §6.4）
        $submissionId = $payload['submission_id'] ?? null;
        if (!is_string($submissionId)) {
            return Response::error('bad_request', 400);
        }

        // 成功したときだけ、履歴の記録と同じトランザクションの中で状態を遷移させる
        $result = $this->answers->submit(
            $caseId,
            (string)$case['status'],
            $submissionId,
            function () use ($caseId): void {
                $this->cases->transitionTo($caseId, 'submitted');
            },
        );

        if ($result['ok'] !== true) {
            if (($result['error'] ?? '') === 'incomplete') {
                // 不足は**パスのみ**返す（値・PII を返さない）
                return Response::ok([
                    'submitted'     => false,
                    'missing'       => $result['missing'] ?? [],
                    'missing_count' => $result['missing_count'] ?? 0,
                ], 200);
            }

            // 固定文言のみ。既存の submission_id・提出日時・提出済みの内容を返さない
            return match ((string)($result['error'] ?? '')) {
                'bad_request'       => Response::error('bad_request', 400),
                'already_submitted' => Response::error('already_submitted', 409),
                'conflict'          => Response::error('conflict', 409),
                default             => Response::error('not_editable', 409),
            };
        }

        if (($result['already'] ?? false) === true) {
            // 同一 submission_id の再送: 状態も履歴も監査も変えない（SSOT §6.4）
            return Response::ok(['submitted' => true, 'already_submitted' => true]);
        }

        $this->logger->info('submitted', [
            'case_number' => (string)$case['case_number'],
            'result_code' => 'ok',
            'ip_hmac'     => $ipHmac,
            'http_status' => 200,
        ]);

        return Response::ok(['submitted' => true, 'already_submitted' => false]);
    }

    /**
     * Cookie の session secret から案件を解決する（SSOT §4.5-B）。
     * 失敗の理由は監査へ残し、外部へは §4.6 の同一応答にする。
     * @return array{session:array<string,mixed>,case:array<string,mixed>}|null
     */
    private function authenticate(Request $req): ?array
    {
        $ipHmac   = $this->rateLimiter->ipHmac($req->clientIp);
        $verified = $this->sessions->verify($req->cookie(Config::COOKIE_NAME));
        if ($verified['row'] === null) {
            $this->audit->record(null, 'token_rejected', $verified['reason'], $ipHmac);

            return null;
        }
        $session = $verified['row'];
        $case    = $this->cases->find((int)$session['intake_case_id']);
        if ($case === null || !in_array((string)$case['status'], CaseService::READABLE, true)) {
            $this->audit->record((int)$session['intake_case_id'], 'token_rejected', 'status', $ipHmac);

            return null;
        }
        $this->sessions->touch((int)$session['id']);

        return ['session' => $session, 'case' => $case];
    }
}
