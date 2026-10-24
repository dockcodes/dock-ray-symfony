<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\Scheduler;

use Dock\Ray\Symfony\Security\SecurityReporter;
use Psr\Log\LoggerInterface;

final class SecurityHandler
{
    public function __construct(private readonly SecurityReporter $reporter, private readonly ?LoggerInterface $logger = null) {}

    public function __invoke(ReportSecurity $message): void
    {
        // Reporter owns schedule, retry and backoff. Never ask Messenger to redeliver on failure.
        $result = $this->reporter->report();
        if (in_array($result['status'], ['failed', 'not_configured', 'invalid_configuration', 'too_large'], true)) {
            $this->logger?->warning('DockRay security report was not sent.', ['status' => $result['status'], 'http_status' => $result['http_status'] ?? null]);
        }
    }
}
