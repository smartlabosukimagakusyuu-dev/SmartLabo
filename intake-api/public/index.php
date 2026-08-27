<?php
/**
 * HP Intake API — フロントコントローラ。
 *
 * ★このファイルは 4B のローカル骨組みであり、本番へは配置していない。
 * ★本番配置時は docs/website/HP_ONBOARDING_INTAKE_DATA_MODEL_V1.md §10.4.1 のとおり
 *   display_errors=Off / display_startup_errors=Off / log_errors=On /
 *   error_log を public_html の外 に設定すること（.user.ini で行う）。
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/Autoload.php';

use SmartLabo\Intake\Config;
use SmartLabo\Intake\Http\Request;
use SmartLabo\Intake\Http\Response;
use SmartLabo\Intake\Kernel;

try {
    $kernel = new Kernel(Config::load());
} catch (\Throwable $e) {
    // 設定不備（鍵未設定など）は fail closed。内容を外部へ出さない
    http_response_code(500);
    foreach (Response::securityHeaders() as $name => $value) {
        header($name . ': ' . $value, true);
    }
    echo json_encode([
        'ok'      => false,
        'error'   => 'server_error',
        'message' => 'ただいま処理できません。時間をおいてお試しください。',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$kernel->app->handle(Request::fromGlobals())->emit();
