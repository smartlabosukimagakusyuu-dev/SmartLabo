<?php
/**
 * ローカル確認用の案件を1件つくる（HP-ONBOARDING-4C）。
 *
 *   php -c intake-api/dev/php.ini intake-api/dev/preview-seed.php
 *
 * ★架空の店舗だけを作る。実在の店舗・個人情報を使わない。
 * ★使い捨てDB（tests/.tmp/preview）にだけ書く。本番・既存DBへ接続しない。
 * ★表示されるリンクは**その端末だけ**のもの。報告書へ貼らない。
 */
declare(strict_types=1);

require_once __DIR__ . '/preview-env.php';
require_once __DIR__ . '/../src/Autoload.php';

previewPutEnv();

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Kernel;

$fresh = in_array('--fresh', $argv, true);
$base  = previewBaseDir();

if ($fresh) {
    foreach (['intake.sqlite', 'intake.sqlite-journal', 'intake.log'] as $name) {
        @unlink($base . '/' . $name);
    }
    echo "使い捨てDBを作り直しました。\n";
}

$kernel = new Kernel(Config::load());

// 架空の案件（実在の店舗・個人情報を使わない）
$caseNumber = 'HP-202608-0001';
$existing   = $kernel->db->pdo()->prepare('SELECT id FROM intake_cases WHERE case_number = :n');
$existing->execute([':n' => $caseNumber]);
$caseId = $existing->fetchColumn();

if ($caseId === false) {
    $caseId = $kernel->cases->create($caseNumber, '架空サロン ハルカゼ');
    echo "架空の案件を作成しました。\n";
} else {
    $caseId = (int)$caseId;
    echo "既存の架空案件を使います。\n";
}

// ご案内リンクを1本発行する（発行のたびに前の分は失効する）
$token = $kernel->tokens->issue($caseId);

echo "\n";
echo "  案件番号 : " . $caseNumber . "\n";
echo "  ポート   : " . PREVIEW_PORT . "（127.0.0.1 のみ）\n";
echo "\n";
echo "  1) 別のターミナルでサーバーを起動する\n";
echo "     php -c intake-api/dev/php.ini -S 127.0.0.1:" . PREVIEW_PORT
    . " -t intake-api/public intake-api/dev/router.php\n";
echo "\n";
echo "  2) 店舗の入力画面（このリンクは共有しない）\n";
echo "     " . PREVIEW_ORIGIN . "/start#" . $token . "\n";
echo "\n";
echo "  3) 内部確認画面\n";
echo "     " . PREVIEW_ORIGIN . "/admin/login\n";
echo "     ID       : " . PREVIEW_ADMIN_ID . "\n";
echo "     パスワード : " . PREVIEW_ADMIN_PASSWORD . "\n";
echo "\n";
echo "  ※ このリンクと資格情報はローカルの使い捨てDB専用です。本番とは無関係です。\n";
