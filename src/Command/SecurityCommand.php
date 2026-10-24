<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\Command;

use Dock\Ray\Symfony\Security\SecurityReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SecurityCommand extends Command
{
    public function __construct(private readonly SecurityReporter $reporter)
    {
        parent::__construct('ray:security');
    }

    protected function configure(): void
    {
        $this->setDescription('Report file integrity and security settings to DockRay (explicit opt-in required)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Send even when the next report is not due');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->reporter->report((bool) $input->getOption('force'));
        $output->writeln('DockRay security: '.$result['status'].(! empty($result['truncated']) ? ' (scan truncated, continues next hour)' : ''));

        if ($result['status'] === 'sent') {
            $this->summary($output, $result);
        }

        return in_array($result['status'], ['sent', 'not_due', 'disabled', 'busy', 'backoff'], true) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Open findings as DockRay sees them. Everything here came from the
     * server: already stripped of control characters by the reporter, and
     * escaped so a `<tag>` in a title cannot style or hide console output.
     *
     * @param array<string, mixed> $result
     */
    private function summary(OutputInterface $output, array $result): void
    {
        $counts = [];

        foreach ((array) ($result['findings'] ?? []) as $severity => $count) {
            $counts[] = $severity.' '.(int) $count;
        }

        $output->writeln('Open findings: '.OutputFormatter::escape(implode(', ', $counts)));

        foreach ((array) ($result['items'] ?? []) as $item) {
            $output->writeln(OutputFormatter::escape(sprintf(
                '  [%s] %s%s',
                $item['severity'] ?? 'unknown',
                $item['title'] ?? $item['code'] ?? '',
                isset($item['subject']) ? ': '.$item['subject'] : ''
            )));
        }

        foreach ((array) ($result['items'] ?? []) as $item) {
            if (! empty($item['url'])) {
                $output->writeln('Details: '.OutputFormatter::escape((string) $item['url']));

                break;
            }
        }
    }
}
