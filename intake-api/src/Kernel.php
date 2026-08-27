<?php
/**
 * HP Intake API — 依存関係の組み立て（DIコンテナの代わり）。
 * public/index.php とテストの両方から同じ組み立てを使う。
 */
declare(strict_types=1);

namespace SmartLabo\Intake;

use SmartLabo\Intake\Admin\AdminApp;
use SmartLabo\Intake\Http\Guard;
use SmartLabo\Intake\Service\AdminAuth;
use SmartLabo\Intake\Service\AnswerService;
use SmartLabo\Intake\Service\Audit;
use SmartLabo\Intake\Service\CaseService;
use SmartLabo\Intake\Service\ExportService;
use SmartLabo\Intake\Service\RateLimiter;
use SmartLabo\Intake\Service\RevisionRequestService;
use SmartLabo\Intake\Service\SessionService;
use SmartLabo\Intake\Service\TokenService;
use SmartLabo\Intake\Support\Clock;
use SmartLabo\Intake\Support\Crypto;
use SmartLabo\Intake\Support\Logger;

final class Kernel
{
    public readonly Db $db;
    public readonly Clock $clock;
    public readonly Logger $logger;
    public readonly Audit $audit;
    public readonly RateLimiter $rateLimiter;
    public readonly TokenService $tokens;
    public readonly SessionService $sessions;
    public readonly CaseService $cases;
    public readonly AnswerService $answers;
    public readonly RevisionRequestService $revisions;
    public readonly AdminAuth $adminAuth;
    public readonly ExportService $export;
    public readonly AdminApp $admin;
    public readonly App $app;

    public function __construct(
        public readonly Config $config,
        ?Clock $clock = null,
    ) {
        $this->clock       = $clock ?? new Clock();
        $this->db          = new Db($config->dbPath);
        (new Migrator($this->db))->migrate();

        $this->logger      = new Logger($config->logPath);
        $this->audit       = new Audit($this->db, $this->clock);
        $this->rateLimiter = new RateLimiter($config, $this->clock);
        $this->tokens      = new TokenService($this->db, $this->clock, $this->audit);
        $this->sessions    = new SessionService($this->db, $this->clock, $this->audit);
        $this->cases       = new CaseService(
            $this->db,
            $this->clock,
            $this->audit,
            $this->tokens,
            $this->sessions,
            new Crypto($config->encKey),
        );
        $this->answers     = new AnswerService($this->db, $this->clock, $this->audit);
        $this->revisions   = new RevisionRequestService($this->db, $this->clock);

        $guard = new Guard($config);

        $this->adminAuth = new AdminAuth($config, $this->db, $this->clock, $this->audit, $this->rateLimiter);
        $this->export    = new ExportService($this->db, $this->clock, $this->cases, $this->answers, $this->revisions);

        $this->admin = new AdminApp(
            $config,
            $guard,
            $this->adminAuth,
            $this->rateLimiter,
            $this->cases,
            $this->answers,
            $this->export,
            $this->revisions,
            $this->tokens,
            $this->audit,
            $this->logger,
        );

        $this->app = new App(
            $config,
            $guard,
            $this->rateLimiter,
            $this->tokens,
            $this->sessions,
            $this->cases,
            $this->answers,
            $this->revisions,
            $this->audit,
            $this->logger,
            $this->clock,
            $this->admin,
        );
    }
}
