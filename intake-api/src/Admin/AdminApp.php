<?php
/**
 * HP Intake API — 内部確認画面（SSOT v1.4 §10.8 / §11.1-7 / §11.1-8 / §11.3）。
 *
 * 経路（すべて /admin/ 配下。店舗向けとパスを分ける）:
 *   GET  /admin/login    ログイン画面
 *   POST /admin/login    ログイン
 *   POST /admin/logout   ログアウト（★GET では受けない）
 *   GET  /admin/         案件一覧
 *   GET  /admin/case     案件詳細
 *   POST /admin/status   状態変更（reviewed / needs_revision）
 *   GET  /admin/export   検証済み JSON のダウンロード
 *
 * 守ること:
 *   - 未認証で到達できる画面を作らない。未認証時に案件の存在を漏らさない
 *   - 状態を変える操作は**すべて POST ＋ CSRF ＋ Origin 検査**
 *   - 管理者ID・パスワード・session・CSRF・回答本文をログへ出さない
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Admin;

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Http\Guard;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Http\Response;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Service\AdminAuth;
use SmartLabo\Intake\Service\AnswerService;
use SmartLabo\Intake\Service\Audit;
use SmartLabo\Intake\Service\CaseService;
use SmartLabo\Intake\Service\ExportService;
use SmartLabo\Intake\Service\RateLimiter;
use SmartLabo\Intake\Support\Logger;

final class AdminApp
{
    /** 外部へ出す文言は固定。内部の理由を混ぜない */
    private const MSG_LOGIN_FAILED = 'IDまたはパスワードが正しくありません。';
    private const MSG_RATE_LIMITED = '短時間に操作が集中しました。しばらくおいてからお試しください。';
    private const MSG_FORBIDDEN    = 'この操作は許可されていません。';
    private const MSG_NOT_FOUND    = 'この画面は表示できません。';
    private const MSG_CONFLICT     = '別の操作で状態が変わっています。最新の内容をご確認ください。';

    public function __construct(
        private readonly Config $config,
        private readonly Guard $guard,
        private readonly AdminAuth $auth,
        private readonly RateLimiter $rateLimiter,
        private readonly CaseService $cases,
        private readonly AnswerService $answers,
        private readonly ExportService $export,
        private readonly Audit $audit,
        private readonly Logger $logger,
    ) {
    }

    public function handle(Request $req): Response
    {
        // 資格情報が未設定なら、管理画面そのものを存在させない（fail closed）
        if (!$this->auth->enabled()) {
            return Response::html(
                View::page('表示できません', View::notice('error', self::MSG_NOT_FOUND), false),
                404
            );
        }

        if ($this->config->requireHttps && !$req->isHttps) {
            return $this->forbidden();
        }

        return match ($req->path) {
            '/admin/login'  => $req->method === 'POST' ? $this->doLogin($req) : $this->loginPage($req),
            '/admin/logout' => $this->doLogout($req),
            '/admin/'       => $this->caseList($req),
            '/admin'        => Response::redirect('/admin/'),
            '/admin/case'   => $this->caseDetail($req),
            '/admin/status' => $this->changeStatus($req),
            '/admin/export' => $this->downloadExport($req),
            default         => Response::html(
                View::page('表示できません', View::notice('error', self::MSG_NOT_FOUND), false),
                404
            ),
        };
    }

    /* ------------------------------------------------------------ ログイン */

    private function loginPage(Request $req, string $message = ''): Response
    {
        if ($req->method !== 'GET') {
            return $this->forbidden();
        }

        $notice = $message === '' ? '' : View::notice('error', $message);

        $body = '<section class="card card--narrow">'
            . '<h1 class="card__title">内部確認画面</h1>'
            . $notice
            . '<form method="post" action="/admin/login" autocomplete="on">'
            . '<div class="field"><label class="field__label" for="admin-id">ID</label>'
            . '<input id="admin-id" name="admin_id" type="text" autocomplete="username" required></div>'
            . '<div class="field"><label class="field__label" for="admin-pw">パスワード</label>'
            . '<input id="admin-pw" name="password" type="password" autocomplete="current-password" required></div>'
            . '<button type="submit" class="btn btn--primary">ログイン</button>'
            . '</form></section>';

        return Response::html(View::page('ログイン', $body, false), $message === '' ? 200 : 401);
    }

    private function doLogin(Request $req): Response
    {
        // ★ログインは form 送信。Origin の厳格検査は行う
        if (!$this->guard->adminPostAllowed($req)) {
            return $this->forbidden();
        }

        $form   = $req->formFields();
        $ipHmac = $this->rateLimiter->ipHmac($req->clientIp);

        $result = $this->auth->login(
            (string)($form['admin_id'] ?? ''),
            (string)($form['password'] ?? ''),
            $ipHmac
        );

        if ($result['ok'] !== true) {
            // ★理由で文言を変えない（IDの存在有無を漏らさない）。混雑だけは案内する
            $this->logger->warn('admin_login', [
                'result_code' => $result['reason'] === 'rate_limited' ? 'rate_limited' : 'invalid',
                'ip_hmac'     => $ipHmac,
                'http_status' => 401,
            ]);

            return $this->loginPage(
                new Request(method: 'GET', path: '/admin/login', isHttps: $req->isHttps),
                $result['reason'] === 'rate_limited' ? self::MSG_RATE_LIMITED : self::MSG_LOGIN_FAILED
            );
        }

        $this->logger->info('admin_login', ['result_code' => 'ok', 'ip_hmac' => $ipHmac, 'http_status' => 303]);

        return Response::redirect('/admin/')
            ->withCookie(Config::ADMIN_COOKIE_NAME, (string)$result['secret'], Config::ADMIN_SESSION_IDLE_TTL, '/admin');
    }

    private function doLogout(Request $req): Response
    {
        // ★GET では受けない（SSOT §10.8 の CSRF 規則5）
        if ($req->method !== 'POST' || !$this->guard->adminPostAllowed($req)) {
            return $this->forbidden();
        }

        $session = $this->auth->verify($req->cookie(Config::ADMIN_COOKIE_NAME));
        if ($session === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->csrfMatches($session, $this->csrfFrom($req))) {
            return $this->forbidden();
        }

        $this->auth->logout((int)$session['id'], $this->rateLimiter->ipHmac($req->clientIp));

        return Response::redirect('/admin/login')
            ->withClearedCookie(Config::ADMIN_COOKIE_NAME, '/admin');
    }

    /* ------------------------------------------------------------ 一覧 */

    private function caseList(Request $req): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }

        $csrf = $this->auth->rotateCsrf((int)$session['id']);
        $rows = $this->cases->listForAdmin();

        $this->audit->record(null, 'admin_viewed', 'ok');

        $items = '';
        foreach ($rows as $row) {
            $number = (string)$row['case_number'];
            $items .= '<tr>'
                . '<td><a href="/admin/case?case=' . rawurlencode($number) . '">' . View::esc($number) . '</a></td>'
                . '<td>' . View::statusLabel((string)$row['status']) . '</td>'
                . '<td>' . View::orDash($row['submitted_at']) . '</td>'
                . '<td>' . ($row['drive_upload_confirmed_at'] === null
                    ? '<span class="muted">未申告</span>'
                    : '<span class="ok">申告済み</span>') . '</td>'
                . '<td>' . View::orDash($row['updated_at']) . '</td>'
                . '<td>' . View::orDash($row['retention_delete_due']) . '</td>'
                . '</tr>';
        }

        if ($items === '') {
            $items = '<tr><td colspan="6" class="muted">案件がありません。</td></tr>';
        }

        $body = '<section class="card">'
            . '<h1 class="card__title">案件一覧</h1>'
            . '<p class="lead">回答の中身はこの画面に出しません。詳細画面でご確認ください。</p>'
            . '<div class="tablewrap"><table class="table">'
            . '<thead><tr><th>案件番号</th><th>状態</th><th>提出日時</th><th>素材</th><th>更新日時</th><th>保持期限</th></tr></thead>'
            . '<tbody>' . $items . '</tbody></table></div>'
            . '</section>'
            . '<div class="endrow">'
            . View::actionForm('/admin/logout', $csrf, [], 'ログアウト', 'btn--quiet')
            . '</div>';

        return Response::html(View::page('案件一覧', $body));
    }

    /* ------------------------------------------------------------ 詳細 */

    private function caseDetail(Request $req): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }

        $case = $this->cases->findByNumber((string)($req->query['case'] ?? ''));
        if ($case === null) {
            return Response::html(
                View::page('表示できません', View::notice('error', self::MSG_NOT_FOUND)),
                404
            );
        }

        $caseId = (int)$case['id'];
        $csrf   = $this->auth->rotateCsrf((int)$session['id']);
        $status = (string)$case['status'];

        $this->audit->record($caseId, 'admin_viewed', 'ok');
        $this->logger->info('admin_viewed', [
            'case_number' => (string)$case['case_number'],
            'result_code' => 'ok',
            'http_status' => 200,
        ]);

        $evaluation = $this->answers->evaluate($caseId);
        $answers    = $this->answers->get($caseId);

        $notice = '';
        $flash  = (string)($req->query['msg'] ?? '');
        if ($flash === 'reviewed') {
            $notice = View::notice('ok', '確認済みにしました。');
        } elseif ($flash === 'revision') {
            $notice = View::notice('ok', '修正依頼中にしました。店舗が再入力できます。');
        } elseif ($flash === 'conflict') {
            $notice = View::notice('warn', self::MSG_CONFLICT);
        } elseif ($flash === 'invalid') {
            $notice = View::notice('warn', 'その操作はこの状態では行えません。');
        }

        $body = $notice
            . $this->summarySection($case, $evaluation, $csrf, $status)
            . $this->missingSection($evaluation)
            . $this->answersSection($answers['sections'])
            . $this->historySection($caseId)
            . '<div class="endrow">'
            . View::actionForm('/admin/logout', $csrf, [], 'ログアウト', 'btn--quiet')
            . '</div>';

        return Response::html(View::page('案件詳細', $body));
    }

    /** @param array<string,mixed> $case */
    private function summarySection(array $case, array $evaluation, string $csrf, string $status): string
    {
        $number   = (string)$case['case_number'];
        $complete = $evaluation['missing'] === [];

        // ★SSOT §5.1 の遷移表に無い操作をボタンにしない。
        //   `submitted` からは reviewed / needs_revision の両方へ行ける。
        //   `reviewed` から needs_revision へは**戻れない**（遷移表に無い）。
        //   押しても必ず失敗するボタンを置かないこと自体が、仕様の説明になる。
        $actions = '';
        if ($status === 'submitted') {
            $actions .= View::actionForm(
                '/admin/status',
                $csrf,
                ['case' => $number, 'to' => 'reviewed'],
                '確認済みにする',
                'btn--primary'
            );
            $actions .= View::actionForm(
                '/admin/status',
                $csrf,
                ['case' => $number, 'to' => 'needs_revision'],
                '修正を依頼する',
                'btn--outline'
            );
        } elseif ($status === 'reviewed') {
            $actions .= '<span class="muted">確認済みです。修正が必要な場合は担当者へご連絡ください。</span>';
        }

        // 書き出しは提出済み以降かつ必須充足のときだけ出す
        $exportable = in_array($status, ExportService::EXPORTABLE, true) && $complete;
        $exportHtml = $exportable
            ? '<a class="btn btn--outline" href="/admin/export?case=' . rawurlencode($number) . '">検証済みJSONを書き出す</a>'
            : '<span class="muted">必須項目が満たされるまで書き出せません</span>';

        return '<section class="card">'
            . '<h1 class="card__title">' . View::esc($number) . '</h1>'
            . View::row('状態', View::statusLabel($status))
            . View::row('契約種別', View::orDash($case['contract_type']))
            . View::row('提出日時', View::orDash($case['submitted_at']))
            . View::row('素材アップロード申告', $case['drive_upload_confirmed_at'] === null
                ? '<span class="muted">未申告</span>'
                : '<span class="ok">' . View::esc($case['drive_upload_confirmed_at']) . '</span>')
            . View::row('保持期限', View::orDash($case['retention_delete_due']))
            . View::row('必須項目', $complete
                ? '<span class="ok">すべて充足</span>'
                : '<span class="ng">' . View::esc(count($evaluation['missing'])) . ' 件不足</span>')
            . '<div class="actions">' . $actions . $exportHtml . '</div>'
            . '</section>';
    }

    private function missingSection(array $evaluation): string
    {
        if ($evaluation['missing'] === []) {
            return '';
        }
        $items = '';
        foreach ($evaluation['missing'] as $path) {
            $items .= '<li>' . View::esc($path) . '</li>';
        }

        return '<section class="card"><h2 class="card__title">不足項目</h2>'
            . '<ul class="list">' . $items . '</ul></section>';
    }

    /** @param array<string,mixed> $sections */
    private function answersSection(array $sections): string
    {
        $titles = [
            'basic' => '基本情報', 'business_hours' => '営業時間・定休日', 'menus' => 'メニュー・料金',
            'staff' => 'スタッフ', 'promotion' => 'お店の特徴', 'design' => 'デザインのご希望',
            'web_links' => 'SNS・外部リンク', 'contact_form' => 'お問い合わせフォーム',
            'privacy' => 'プライバシー', 'image_metadata' => '写真・素材', 'rights' => '権利・同意',
        ];

        $out = '';
        foreach (Migrator::ANSWER_SECTIONS as $key) {
            $out .= '<section class="card"><h2 class="card__title">' . View::esc($titles[$key] ?? $key) . '</h2>'
                . $this->renderValue($sections[$key] ?? null)
                . '</section>';
        }

        return $out;
    }

    /**
     * 回答の値を安全に描く。
     * ★どの分岐でも必ず esc() を通す。生の値を HTML へ入れない。
     */
    private function renderValue(mixed $value, int $depth = 0): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '<p class="muted">未入力</p>';
        }
        if (is_bool($value)) {
            return '<p>' . ($value ? 'はい' : 'いいえ') . '</p>';
        }
        if (is_scalar($value)) {
            $text = (string)$value;

            return strncmp($text, 'https://', 8) === 0
                ? '<p>' . View::link($text) . '</p>'
                : '<p>' . View::escLines($text) . '</p>';
        }
        if ($depth > 4) {
            return '<p class="muted">…</p>';
        }
        if (!is_array($value)) {
            return '<p class="muted">—</p>';
        }

        $rows = '';
        foreach ($value as $key => $child) {
            $label = is_int($key) ? '#' . ($key + 1) : (string)$key;
            $rows .= '<div class="row"><span class="row__key">' . View::esc($label) . '</span>'
                . '<span class="row__val">' . $this->renderValue($child, $depth + 1) . '</span></div>';
        }

        return $rows;
    }

    private function historySection(int $caseId): string
    {
        $rows = $this->answers->historyRows($caseId);

        $items = '';
        foreach ($rows as $row) {
            $items .= '<tr>'
                . '<td>' . View::esc($row['event_type']) . '</td>'
                . '<td>' . View::esc($row['submitted_at']) . '</td>'
                . '<td>' . View::esc($row['result_code']) . '</td>'
                . '<td>' . View::orDash($row['field_count']) . '</td>'
                . '<td>' . View::orDash($row['missing_count']) . '</td>'
                . '</tr>';
        }
        if ($items === '') {
            $items = '<tr><td colspan="5" class="muted">記録がありません。</td></tr>';
        }

        $auditTotal = $this->audit->countFor($caseId);

        return '<section class="card"><h2 class="card__title">提出履歴</h2>'
            . '<div class="tablewrap"><table class="table">'
            . '<thead><tr><th>種別</th><th>日時</th><th>結果</th><th>充足</th><th>不足</th></tr></thead>'
            . '<tbody>' . $items . '</tbody></table></div>'
            . '<p class="muted">監査イベント ' . View::esc($auditTotal) . ' 件（明細はこの画面に出しません）</p>'
            . '</section>';
    }

    /* ------------------------------------------------------------ 状態変更 */

    private function changeStatus(Request $req): Response
    {
        // ★POST のみ。GET では状態を変えない
        if ($req->method !== 'POST' || !$this->guard->adminPostAllowed($req)) {
            return $this->forbidden();
        }

        $session = $this->auth->verify($req->cookie(Config::ADMIN_COOKIE_NAME));
        if ($session === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->csrfMatches($session, $this->csrfFrom($req))) {
            return $this->forbidden();
        }
        $this->auth->touch((int)$session['id']);

        $form   = $req->formFields();
        $number = (string)($form['case'] ?? '');
        $to     = (string)($form['to'] ?? '');

        // 独自 status を作らない。許すのはこの2つだけ
        $allowed = [
            'reviewed'       => 'reviewed',
            'needs_revision' => 'revision_requested',
        ];
        if (!isset($allowed[$to])) {
            return $this->forbidden();
        }

        $case = $this->cases->findByNumber($number);
        if ($case === null) {
            return Response::html(
                View::page('表示できません', View::notice('error', self::MSG_NOT_FOUND)),
                404
            );
        }

        $result = $this->cases->adminChangeStatus((int)$case['id'], $to, $allowed[$to]);

        $msg = match (true) {
            $result['ok'] === true && $to === 'reviewed' => 'reviewed',
            $result['ok'] === true                       => 'revision',
            ($result['error'] ?? '') === 'conflict'      => 'conflict',
            default                                      => 'invalid',
        };

        // ★理由をそのまま記録する（失敗をすべて conflict と書かない）
        $this->logger->info('case_status_changed', [
            'case_number' => (string)$case['case_number'],
            'result_code' => $result['ok'] === true ? 'ok' : (string)($result['error'] ?? 'invalid'),
            'http_status' => 303,
        ]);

        return Response::redirect('/admin/case?case=' . rawurlencode($number) . '&msg=' . $msg);
    }

    /* ------------------------------------------------------------ 書き出し */

    private function downloadExport(Request $req): Response
    {
        [$session, $fail] = $this->requireSession($req, 'GET');
        if ($fail !== null) {
            return $fail;
        }
        unset($session);

        $case = $this->cases->findByNumber((string)($req->query['case'] ?? ''));
        if ($case === null) {
            return Response::html(
                View::page('表示できません', View::notice('error', self::MSG_NOT_FOUND)),
                404
            );
        }

        $caseId = (int)$case['id'];
        $result = $this->export->export($caseId);

        if ($result['ok'] !== true) {
            $this->audit->record($caseId, 'export_generated', 'invalid');

            return Response::html(
                View::page('書き出せません', View::notice(
                    'warn',
                    'この案件はまだ書き出せません。提出と必須項目をご確認ください。'
                )),
                409
            );
        }

        $this->audit->record($caseId, 'export_generated', 'ok');
        // ★JSON 本文をログへ出さない。案件番号と件数だけ
        $this->logger->info('export_generated', [
            'case_number' => (string)$case['case_number'],
            'result_code' => 'ok',
            'http_status' => 200,
        ]);

        return Response::download(
            (string)$result['json'],
            (string)$result['file_name'],
            'application/json; charset=UTF-8',
            ['X-Intake-Export-Sha256' => (string)$result['sha256']]
        );
    }

    /* ------------------------------------------------------------ 補助 */

    /**
     * 認証を要求する。未認証ならログイン画面へ送る。
     * ★未認証のときに案件の有無を判定しない（存在を漏らさない）。
     * @return array{0:array<string,mixed>,1:?Response}
     */
    private function requireSession(Request $req, string $method): array
    {
        if ($req->method !== $method) {
            return [[], $this->forbidden()];
        }

        // 他サイトからの埋め込み・遷移は拒否する。
        // ★GET は状態を変えないため、ここでの主な守りは**認証**と
        //   `X-Frame-Options: DENY` / `frame-ancestors 'none'` / Cookie の SameSite=Strict である。
        //   Fetch Metadata が付く環境では、そこも合わせて塞いでおく。
        $site = $req->header('Sec-Fetch-Site');
        if ($site !== null && strtolower(trim($site)) === 'cross-site') {
            return [[], $this->forbidden()];
        }

        $session = $this->auth->verify($req->cookie(Config::ADMIN_COOKIE_NAME));
        if ($session === null) {
            return [[], Response::redirect('/admin/login')];
        }
        $this->auth->touch((int)$session['id']);

        return [$session, null];
    }

    private function csrfFrom(Request $req): ?string
    {
        $form = $req->formFields();

        return isset($form['csrf_token']) ? (string)$form['csrf_token'] : null;
    }

    private function forbidden(): Response
    {
        return Response::html(
            View::page('許可されていません', View::notice('error', self::MSG_FORBIDDEN), false),
            403
        );
    }
}
