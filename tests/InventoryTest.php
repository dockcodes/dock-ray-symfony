<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\Tests;

use Dock\Ray\Inventory\InventoryReporter;
use Dock\Ray\Options;
use Dock\Ray\Symfony\Command\InventoryCommand;
use Dock\Ray\Symfony\DependencyInjection\Configuration;
use Dock\Ray\Symfony\DependencyInjection\RayExtension;
use Dock\Ray\Symfony\Inventory\InventoryFactory;
use Dock\Ray\Symfony\Scheduler\InventoryHandler;
use Dock\Ray\Symfony\Scheduler\InventorySchedule;
use Dock\Ray\Symfony\Scheduler\ReportInventory;
use Dock\Ray\Symfony\RayBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\Kernel;

final class InventoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/dockray-symfony-test-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/vendor/composer', 0700, true);
        file_put_contents($this->root.'/composer.json', '{"name":"private/application"}');
        file_put_contents($this->root.'/composer.lock', '{"packages":[{"name":"symfony/http-kernel"},{"name":"company/private"}],"packages-dev":[{"name":"phpunit/phpunit"}]}');
        file_put_contents($this->root.'/vendor/composer/installed.json', '{"packages":[{"name":"symfony/http-kernel","version":"v7.4.0"},{"name":"company/private","version":"1.0.0","dist":{"url":"https://user:secret@example.com"}},{"name":"phpunit/phpunit","version":"11.0.0"}]}');
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function test_config_defaults_are_opt_in_and_point_to_dockray(): void
    {
        $config = (new Processor)->processConfiguration(new Configuration, [[]]);
        $this->assertFalse($config['inventory']['enabled']);
        $this->assertNull($config['inventory']['site_url']);
        $this->assertSame('https://dockray.io', $config['url']);
        $reporter = InventoryFactory::create($config + $config['inventory'], $this->root, $this->root.'/state');
        $this->assertSame(['status' => 'disabled'], $reporter->report());
        $this->assertDirectoryDoesNotExist($this->root.'/state');
    }

    public function test_command_reports_only_production_packages_and_respects_the_interval(): void
    {
        $http = new MockHttpClient(function ($method, $url, $options) {
            $this->assertSame('https://dockray.io/api/v1/project/inventory', $url);
            $this->assertSame(['Authorization: Bearer secret'], $options['normalized_headers']['authorization']);
            $this->assertSame(0, $options['max_redirects']);
            $payload = json_decode($options['body'], true);
            $this->assertSame('php', $payload['platform']);
            $this->assertSame([['type' => 'package', 'slug' => 'symfony/http-kernel', 'version' => '7.4.0', 'active' => true]], $payload['components']);
            $this->assertStringNotContainsString('secret', $options['body']);

            return new MockResponse('{"success":true,"vulnerable":1,"next_report_in":43200}');
        });
        $reporter = new InventoryReporter(new Options([
            'token' => 'project', 'private_key' => 'secret', 'inventory_enabled' => true,
            'inventory_site_url' => 'https://app.example.com', 'inventory_exclude_packages' => ['company/*'],
        ]), $this->root, $this->root.'/state', 'php', $http);
        $tester = new CommandTester(new InventoryCommand($reporter));
        $this->assertSame(0, $tester->execute(['--force' => true]));
        $this->assertStringContainsString('sent', $tester->getDisplay());
        $this->assertStringNotContainsString('secret', $tester->getDisplay());
        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('not_due', $tester->getDisplay());
        $this->assertSame(1, $http->getRequestsCount());
        (new InventoryHandler($reporter))(new ReportInventory);
        $this->assertSame(1, $http->getRequestsCount());
    }

    public function test_schedule_is_empty_when_disabled_and_hourly_when_enabled(): void
    {
        $this->assertCount(0, (new InventorySchedule(false))->getSchedule()->getRecurringMessages());
        $provider = new InventorySchedule(true);
        $schedule = $provider->getSchedule();
        $this->assertSame($schedule, $provider->getSchedule());
        $messages = $schedule->getRecurringMessages();
        $this->assertCount(1, $messages);
        $message = array_values($messages)[0];
        $context = new \Symfony\Component\Scheduler\Generator\MessageContext('dockray_inventory', $message->getId(), $message->getTrigger(), new \DateTimeImmutable);
        $this->assertInstanceOf(ReportInventory::class, iterator_to_array($message->getMessages($context))[0]);
        $trigger = $message->getTrigger();
        $first = $trigger->getNextRunDate(new \DateTimeImmutable('2026-01-01 12:00:00'));
        $next = $trigger->getNextRunDate($first);
        $this->assertSame(3600, $next->getTimestamp() - $first->getTimestamp());
    }

    public function test_bundle_compiles_with_environment_opt_in_and_registers_command_and_scheduler_transport(): void
    {
        foreach (['0', '1'] as $enabled) {
            $_ENV['DOCKRAY_TEST_INVENTORY'] = $enabled;
            $kernel = new InventoryKernel($this->root);
            try {
                $kernel->boot();
                $application = new Application($kernel);
                $tester = new CommandTester($application->find('ray:inventory'));
                $this->assertSame($enabled === '0' ? 0 : 1, $tester->execute([]));
                $this->assertStringContainsString($enabled === '0' ? 'disabled' : 'not_configured', $tester->getDisplay());
                $debug = new CommandTester($application->find('debug:scheduler'));
                $this->assertSame(0, $debug->execute([]));
                $this->assertStringContainsString('dockray_inventory', $debug->getDisplay());
                $this->assertTrue($kernel->getContainer()->get('test.service_container')->has('messenger.transport.scheduler_dockray_inventory'));
            } finally {
                $kernel->shutdown();
                unset($_ENV['DOCKRAY_TEST_INVENTORY']);
            }
        }
    }
}

final class InventoryKernel extends Kernel
{
    public function __construct(private readonly string $root)
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle;
        yield new RayBundle;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test', 'test' => true, 'scheduler' => true,
                'messenger' => ['transports' => []],
            ]);
            $container->loadFromExtension('ray', [
                'inventory' => ['enabled' => '%env(bool:DOCKRAY_TEST_INVENTORY)%', 'site_url' => 'https://app.example.com'],
            ]);
        });
    }

    public function getProjectDir(): string
    {
        return $this->root;
    }
    public function getCacheDir(): string
    {
        return $this->root.'/cache';
    }
    public function getLogDir(): string
    {
        return $this->root.'/log';
    }
}
