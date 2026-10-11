<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\Command;

use Dock\Ray\Inventory\InventoryReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class InventoryCommand extends Command
{
    public function __construct(private readonly InventoryReporter $reporter)
    {
        parent::__construct('ray:inventory');
    }

    protected function configure(): void
    {
        $this->setDescription('Report installed production Composer packages to DockRay (explicit opt-in required)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Send even when an unchanged inventory is not due');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->reporter->report((bool) $input->getOption('force'));
        $output->writeln('DockRay inventory: '.$result['status']);

        return in_array($result['status'], ['sent', 'not_due', 'disabled', 'busy', 'backoff'], true) ? Command::SUCCESS : Command::FAILURE;
    }
}
