<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\Security;

use Dock\Ray\AuthData;
use Dock\Ray\Symfony\RayBundle;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

final class SecurityFactory
{
    /**
     * Builds the reporter from the bundle configuration. Cheap on purpose - the
     * `kernel.terminate` listener builds it on page views - so the HTTP client
     * is only created when a report is actually sent.
     *
     * @param array{token?: string|null, private_key?: string|null, url?: string|null, enabled?: bool, site_url?: string|null, inventory_site_url?: string|null, release?: string|null, upload_directories?: array<string>} $config
     * @param array<string, string> $bundles `%kernel.bundles%`
     */
    public static function create(array $config, string $projectDirectory, string $stateDirectory, bool $debug, array $bundles,
        ?LoggerInterface $logger = null, ?CacheItemPoolInterface $cache = null): SecurityReporter
    {
        $token = trim((string) ($config['token'] ?? ''));
        $privateKey = trim((string) ($config['private_key'] ?? ''));
        $auth = null;
        $invalid = false;

        if ($token !== '' && $privateKey !== '') {
            try {
                $auth = AuthData::create($token, $privateKey, (string) ($config['url'] ?? '') ?: AuthData::DEFAULT_URL);
            } catch (\InvalidArgumentException $exception) {
                $invalid = true;
            }
        }

        // Explicit configured URL only: never infer the site from an incoming Host header.
        $siteUrl = $config['site_url'] ?? null;

        if (! is_string($siteUrl) || $siteUrl === '') {
            $siteUrl = $config['inventory_site_url'] ?? null;
        }

        return new SecurityReporter($auth, (bool) ($config['enabled'] ?? false), $projectDirectory, $stateDirectory, [
            'site_url' => is_string($siteUrl) && $siteUrl !== '' ? $siteUrl : null,
            'release' => isset($config['release']) && is_scalar($config['release']) ? (string) $config['release'] : null,
            'debug' => $debug,
            'bundles' => array_values($bundles),
            'upload_directories' => (array) ($config['upload_directories'] ?? []),
            'agent_version' => RayBundle::VERSION,
            'invalid_configuration' => $invalid,
        ], null, $logger, $cache);
    }
}
