<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\Scheduler;

use Dock\Ray\Inventory\InventoryReporter;
use Psr\Log\LoggerInterface;

final class InventoryHandler
{
    public function __construct(private readonly InventoryReporter $reporter, private readonly ?LoggerInterface $logger = null) {}

    public function __invoke(ReportInventory $message): void
    {
        // Reporter owns retry/backoff. Never ask Messenger to redeliver on failure.
        $result = $this->reporter->report();
        if (in_array($result['status'], ['failed', 'not_configured', 'invalid_configuration'], true)) {
            $this->logger?->warning('DockRay inventory was not sent.', ['status' => $result['status'], 'http_status' => $result['http_status'] ?? null]);
        }
    }
}
