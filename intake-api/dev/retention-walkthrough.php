<?php
/**
 * 保持削除の通し確認（HP-ONBOARDING-4F-PRE / SSOT v1.7 §9）
 *
 *   php -c intake-api/dev/php.ini intake-api/dev/retention-walkthrough.php
 *
 * ★**使い捨てのDBを毎回作り直して**確認する。実行のたびに新しいディレクトリを作り、
 *   終わったら消す。**本番・既存DB（dev/.preview/ を含む）へは一切接続しない。**
 * ★架空の店舗・架空のメール・架空のフォルダURLだけを使う。
 * ★表示される案件番号・リンクはこの端末の使い捨てDB専用。報告書へ貼らない。
 *
 * 何を確かめるか（brief §14 の18段）:
 *   案件作成 → 店舗入力 → 提出 → reviewed → locked → 削除予定日 →
 *   flags=false で拒否 → flags=true → 期限前で拒否 → 時計を進める →
 *   DELETE <案件番号> → 機密情報の削除 → closed/deleted_at →
 *   export 拒否 → token/session 拒否 → DBとログの残留確認 →
 *   監査13か月清掃 → 管理session清掃
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/Autoload.php';

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Kernel;
use SmartLabo\Intake\Support\Clock;

/* ---------------------------------------------------------------- 準備 */

const WT_ORIGIN   = 'http://127.0.0.1:8788';
const WT_ADMIN_ID = 'walkthrough-admin';
const WT_ADMIN_PW = 'walkthrough-only-password-0123456789';
/** 削除されたことを確かめるための目印（架空・他に出てこない文字列） */
const WT_MARKER   = 'WALKTHROUGHMARKER0001';
const WT_EMAIL    = 'materials@example.invalid';
const WT_DRIVE    = 'https://drive.google.com/drive/folders/FAKE-WALKTHROUGH-000';

/** 時計を自由に動かせる Clock */
final class WalkClock extends Clock
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

$step = 0;
$bad  = 0;

$check = static function (string $label, bool $ok) use (&$step, &$bad): void {
    ++$step;
    if (!$ok) {
        ++$bad;
    }
    printf("  %2d. [%s] %s\n", $step, $ok ? 'OK' : 'NG', $label);
};

$base = __DIR__ . '/.walkthrough-' . getmypid();
if (is_dir($base)) {
    foreach (glob($base . '/*') ?: [] as $f) {
        @unlink($f);
    }
}
mkdir($base . '/logs', 0700, true);

$cleanup = static function () use ($base): void {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $path) {
        $path->isDir() ? @rmdir($path->getPathname()) : @unlink($path->getPathname());
    }
    @rmdir($base);
};

$clock  = new WalkClock();
$config = Config::load([
    'db_path'         => $base . '/walkthrough.sqlite',
    'ip_hmac_key'     => 'walkthrough-only-ip-hmac-key-0123456789',
    'enc_key'         => 'walkthrough-only-enc-key-0123456789abcd',
    'allowed_origins' => [WT_ORIGIN],
    'rate_limit_dir'  => $base . '/ratelimit',
    'log_path'        => $base . '/logs/intake.log',
    'require_https'   => false,
    'admin_id'        => WT_ADMIN_ID,
    // ★hash はその場で作る。Git へ hash も入れない
    'admin_password_hash' => password_hash(
        WT_ADMIN_PW,
        defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT
    ),
    // まずは**無効**の状態から始める（7段目で拒否されることを確かめる）
    'retention_actions_enabled' => false,
    'backup_policy_confirmed'   => false,
]);

$kernel = new Kernel($config, $clock);

/* -------------------------------------------------- リクエスト組み立て */

$post = static function (string $path, array $body, array $cookies = []): Request {
    return new Request(
        method: 'POST',
        path: $path,
        headers: ['Content-Type' => 'application/json', 'Origin' => WT_ORIGIN],
        body: (string)json_encode($body),
        cookies: $cookies,
        isHttps: false,
        clientIp: '127.0.0.1',
    );
};
$get = static function (string $path, array $cookies = [], array $query = []): Request {
    return new Request(
        method: 'GET',
        path: $path,
        headers: ['Origin' => WT_ORIGIN, 'Sec-Fetch-Site' => 'same-origin'],
        body: '',
        cookies: $cookies,
        isHttps: false,
        clientIp: '127.0.0.1',
        query: $query,
    );
};
$formPost = static function (string $path, array $fields, array $cookies = []): Request {
    return new Request(
        method: 'POST',
        path: $path,
        headers: ['Content-Type' => 'application/x-www-form-urlencoded', 'Origin' => WT_ORIGIN],
        body: http_build_query($fields),
        cookies: $cookies,
        isHttps: false,
        clientIp: '127.0.0.1',
    );
};
$csrfOf = static function (string $html): string {
    preg_match('/name="csrf_token" value="([A-Za-z0-9_-]{43})"/', $html, $m);

    return (string)($m[1] ?? '');
};

echo "HP Intake — 保持削除の通し確認（架空案件・使い捨てDB）\n";
echo str_repeat('-', 64) . "\n";
echo "  DB: " . $base . "/walkthrough.sqlite\n\n";

/* -------------------------------------------------- 1. 案件作成 */

$number = 'HP-202608-0001';
$caseId = $kernel->cases->create($number, '架空サロン ' . WT_MARKER);
$kernel->cases->setDriveFolder($caseId, WT_DRIVE, $number . ' 素材', WT_EMAIL);
$token  = $kernel->tokens->issue($caseId);
$check('案件を作成し、ご案内リンクを発行した', $kernel->cases->find($caseId) !== null);

/* -------------------------------------------------- 2. 店舗入力 */

$start   = $kernel->app->handle($post('/session/start', ['token' => $token]));
$cookies = [Config::COOKIE_NAME => (string)$start->cookies[0]['value']];

$sections = require __DIR__ . '/walkthrough-answers.php';
$save     = $kernel->app->handle($post('/answers/save', ['version' => 1, 'sections' => $sections], $cookies));
$check('店舗が入力し、途中保存できた', $start->status === 200 && $save->status === 200);

/* -------------------------------------------------- 3. 提出 */

$submit = $kernel->app->handle($post('/submit', ['submission_id' => (function (): string {
    $b    = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
})()], $cookies));
$check('提出できた（status = submitted）',
    $submit->status === 200 && $kernel->cases->find($caseId)['status'] === 'submitted');

/* -------------------------------------------------- 4. reviewed */

$login       = $kernel->app->handle($formPost('/admin/login', ['admin_id' => WT_ADMIN_ID, 'password' => WT_ADMIN_PW]));
$adminCookie = [Config::ADMIN_COOKIE_NAME => (string)$login->cookies[0]['value']];

$detail = $kernel->app->handle($get('/admin/case', $adminCookie, ['case' => $number]));
$kernel->app->handle($formPost('/admin/status', [
    'csrf_token' => $csrfOf((string)$detail->rawBody), 'case' => $number, 'to' => 'reviewed',
], $adminCookie));
$check('管理画面から確認済みにした（status = reviewed）',
    $kernel->cases->find($caseId)['status'] === 'reviewed');

/* -------------------------------------------------- 5. locked */

$lockForm = $kernel->app->handle($get('/admin/lock', $adminCookie, ['case' => $number]));
$kernel->app->handle($formPost('/admin/lock/send', [
    'csrf_token' => $csrfOf((string)$lockForm->rawBody), 'case' => $number, 'confirm_case' => $number,
], $adminCookie));
$case = $kernel->cases->find($caseId);
$check('入力を確定した（status = locked・token と店舗 session が失効）',
    $case['status'] === 'locked'
    && $kernel->tokens->activeCount($caseId) === 0
    && $kernel->sessions->activeCount($caseId) === 0);

/* -------------------------------------------------- 6. 削除予定日 */

$detail2 = $kernel->app->handle($get('/admin/case', $adminCookie, ['case' => $number]));
$due     = gmdate('Y-m-d', $clock->now() + (40 * 86400));
$kernel->app->handle($formPost('/admin/retention/due', [
    'csrf_token' => $csrfOf((string)$detail2->rawBody), 'case' => $number, 'due' => $due,
], $adminCookie));
$check('削除予定日（' . $due . '）を登録した',
    $kernel->cases->find($caseId)['retention_delete_due'] === $due);

/* -------------------------------------------------- 7. flags=false で拒否 */

$denied = $kernel->app->handle($get('/admin/purge', $adminCookie, ['case' => $number]));
$check('フラグ未設定では削除画面が出ない（403）', $denied->status === 403);

/* -------------------------------------------------- 8. ローカル override で有効化 */

$enabledConfig = Config::load([
    'db_path'         => $base . '/walkthrough.sqlite',
    'ip_hmac_key'     => 'walkthrough-only-ip-hmac-key-0123456789',
    'enc_key'         => 'walkthrough-only-enc-key-0123456789abcd',
    'allowed_origins' => [WT_ORIGIN],
    'rate_limit_dir'  => $base . '/ratelimit',
    'log_path'        => $base . '/logs/intake.log',
    'require_https'   => false,
    'admin_id'        => WT_ADMIN_ID,
    'admin_password_hash' => $config->adminPasswordHash,
    'retention_actions_enabled' => true,
    'backup_policy_confirmed'   => true,
]);
$kernel = new Kernel($enabledConfig, $clock);
$check('ローカル override で削除を有効にした', $kernel->config->retentionEnabled());

/* -------------------------------------------------- 9. 期限前は拒否 */

$early = $kernel->app->handle($get('/admin/purge', $adminCookie, ['case' => $number]));
$check('期限前は削除できない（409）', $early->status === 409);

/* -------------------------------------------------- 10. 時計を進める */

$clock->advance(41 * 86400);
// ★ 41日進めたので管理 session も期限切れになっている（idle 30分・絶対8時間）。
//   これは正しい振る舞いなので、入り直してから続ける。
$relogin     = $kernel->app->handle($formPost('/admin/login', ['admin_id' => WT_ADMIN_ID, 'password' => WT_ADMIN_PW]));
$adminCookie = [Config::ADMIN_COOKIE_NAME => (string)$relogin->cookies[0]['value']];
$check('テスト時計を進めて期限を到来させた',
    $kernel->retention->canPurge($kernel->cases->find($caseId))['ok'] === true);

/* -------------------------------------------------- 11. 確認入力 */

$purgeForm = $kernel->app->handle($get('/admin/purge', $adminCookie, ['case' => $number]));
$check('確認画面に「元に戻せない」「Drive は別作業」「バックアップ」の説明がある',
    $purgeForm->status === 200
    && str_contains((string)$purgeForm->rawBody, '元に戻せません')
    && str_contains((string)$purgeForm->rawBody, 'Google Drive の実ファイルはここでは消えません')
    && str_contains((string)$purgeForm->rawBody, 'バックアップにも保持期限があります'));

$wrong = $kernel->app->handle($formPost('/admin/purge/send', [
    'csrf_token' => $csrfOf((string)$purgeForm->rawBody), 'case' => $number, 'confirm' => 'delete ' . $number,
], $adminCookie));
$check('確認入力が一致しないと実行されない（400）',
    $wrong->status === 400 && $kernel->cases->find($caseId)['deleted_at'] === null);

/* -------------------------------------------------- 12. 削除の実行 */

$purged = $kernel->app->handle($formPost('/admin/purge/send', [
    'csrf_token' => $csrfOf((string)$wrong->rawBody), 'case' => $number, 'confirm' => 'DELETE ' . $number,
], $adminCookie));

$counts = [];
foreach (['intake_answers', 'intake_tokens', 'intake_sessions',
          'intake_revision_requests', 'intake_submission_history'] as $table) {
    $stmt = $kernel->db->pdo()->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE intake_case_id = :id');
    $stmt->execute([':id' => $caseId]);
    $counts[$table] = (int)$stmt->fetchColumn();
}
$check('機密情報を削除した（5表とも0件）', $purged->status === 303 && array_sum($counts) === 0);

/* -------------------------------------------------- 13. closed / deleted_at */

$after = $kernel->cases->find($caseId);
$check('status = closed / deleted_at 記録 / Drive 暗号文なし / 店舗名なし',
    $after['status'] === 'closed'
    && $after['deleted_at'] !== null
    && $after['drive_folder_url_enc'] === null
    && $after['drive_shared_email_enc'] === null
    && !str_contains((string)$after['shop_display_name'], WT_MARKER));

/* -------------------------------------------------- 14. export 拒否 */

$export = $kernel->app->handle($get('/admin/export', $adminCookie, ['case' => $number]));
$check('削除済み案件は書き出せない（409）',
    $export->status === 409 && $kernel->export->export($caseId)['error'] === 'deleted');

/* -------------------------------------------------- 15. token / session 拒否 */

$oldCase   = $kernel->app->handle($get('/case', $cookies));
$reissue   = $kernel->app->handle($get('/admin/reissue', $adminCookie, ['case' => $number]));
$check('古い店舗 session も再発行も通らない（404 / 409）',
    $oldCase->status === 404 && $reissue->status === 409);

/* -------------------------------------------------- 16. 残留確認 */

// ★接続は閉じない。journal_mode=DELETE ＋ 自動コミットのため、
//   コミット済みの内容はすでに本体ファイルへ書かれている。
$raw = (string)file_get_contents($base . '/walkthrough.sqlite');
$log = (string)file_get_contents($base . '/logs/intake.log');

$leftInDb  = [];
foreach ([WT_MARKER, WT_EMAIL, 'drive.google.com', '架空県架空市架空町'] as $needle) {
    if (str_contains($raw, $needle)) {
        $leftInDb[] = $needle;
    }
}
$leftInLog = [];
foreach ([WT_MARKER, WT_EMAIL, 'drive.google.com', $token, 'DELETE ' . $number] as $needle) {
    if (str_contains($log, $needle)) {
        $leftInLog[] = $needle;
    }
}
$check('DBファイルに架空の目印が残っていない' . ($leftInDb === [] ? '' : '（残: ' . implode(',', $leftInDb) . '）'),
    $leftInDb === [] && str_contains($raw, $number));
$check('ログに PII・token・確認文が残っていない' . ($leftInLog === [] ? '' : '（残: ' . implode(',', $leftInLog) . '）'),
    $leftInLog === [] && str_contains($log, 'retention_purged'));

/* -------------------------------------------------- 17. 監査の13か月清掃 */

$kernel->db->pdo()->prepare(
    'INSERT INTO intake_audit_events (intake_case_id, event_type, result_code, ip_hmac, created_at)
     VALUES (NULL, :e, :r, :ip, :at)'
)->execute([':e' => 'admin_viewed', ':r' => 'ok', ':ip' => str_repeat('a', 32), ':at' => '2020-01-01T00:00:00Z']);

$before = $kernel->retention->countAuditDue();
$maint  = $kernel->app->handle($get('/admin/maintenance', $adminCookie));
$audit  = $kernel->app->handle($formPost('/admin/maintenance/audit', [
    'csrf_token' => $csrfOf((string)$maint->rawBody),
], $adminCookie));
$check('13か月を過ぎた監査ログだけを削除した（' . $before . ' 件）',
    $audit->status === 200 && $before >= 1 && $kernel->retention->countAuditDue() === 0);

/* -------------------------------------------------- 18. 管理 session の清掃 */

$stale = $kernel->app->handle($formPost('/admin/login', ['admin_id' => WT_ADMIN_ID, 'password' => WT_ADMIN_PW]));
$kernel->db->pdo()->prepare('UPDATE intake_admin_sessions SET expires_at = :past WHERE session_hash = :h')
    ->execute([
        ':past' => '2026-01-01T00:00:00Z',
        ':h'    => hash('sha256', (string)$stale->cookies[0]['value']),
    ]);

$maint2   = $kernel->app->handle($get('/admin/maintenance', $adminCookie));
$sessions = $kernel->app->handle($formPost('/admin/maintenance/sessions', [
    'csrf_token' => $csrfOf((string)$maint2->rawBody),
], $adminCookie));
$stillIn  = $kernel->app->handle($get('/admin/', $adminCookie));
$check('期限切れの管理 session だけを削除し、実行者は締め出されない',
    $sessions->status === 200
    && $kernel->retention->countAdminSessionsDue() === 0
    && $stillIn->status === 200);

/* -------------------------------------------------- 後始末 */

echo "\n";
echo str_repeat('-', 64) . "\n";
printf("  %d 段 / NG %d 件\n", $step, $bad);

// ★PDOStatement が生きている間は接続が解放されない
//   （Windows ではファイルを消せない）。参照を手放してから消す。
$kernel->db->close();
unset($kernel, $stmt, $case, $after, $counts, $purgeForm, $maint, $maint2);
gc_collect_cycles();
$cleanup();
echo is_dir($base)
    ? "  ★使い捨てDBが残りました: " . $base . "
"
    : "  使い捨てDBを削除しました。
";

exit($bad === 0 ? 0 : 1);
