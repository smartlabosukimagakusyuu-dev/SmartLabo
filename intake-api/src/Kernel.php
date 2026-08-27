<?php
/**
 * HP Intake API — 依存関係の組み立て（DIコンテナの代わり）。
 * public/index.php とテストの両方から同じ組み立てを使う。
 */
declare(strict_types=1);

namespace SmartLabo\Intake;

use SmartLabo\Intake\Http\Guard;
use SmartLabo\Intake\Service\AnswerService;
use SmartLabo\Intake\Service\Audit;
use SmartLabo\Intake\Service\CaseService;
use SmartLabo\Intake\Service\RateLimiter;
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

        $this->app = new App(
            $config,
            new Guard($config),
            $this->rateLimiter,
            $this->tokens,
            $this->sessions,
            $this->cases,
            $this->answers,
            $this->audit,
            $this->logger,
            $this->clock,
        );
    }
}
