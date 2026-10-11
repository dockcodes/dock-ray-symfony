# DockRay for Symfony

Reports Symfony exceptions, console errors and HTTP transactions to
[DockRay](https://dockray.io).

## Installation

```shell
composer require dockcodes/dock-ray-symfony
```

Without Symfony Flex, register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Dock\Ray\Symfony\RayBundle::class => ['all' => true],
];
```

## Configuration

`config/packages/ray.yaml`:

```yaml
ray:
    token: '%env(RAY_TOKEN)%'
    private_key: '%env(RAY_PRIVATE_KEY)%'
    traces_sample_rate: 0.2
    ignore_exceptions:
        - Symfony\Component\HttpKernel\Exception\NotFoundHttpException
```

Every key is optional. Without a token and a private key the bundle loads but
sends nothing.

| Key | Default | Meaning |
|---|---|---|
| `environment` | `%kernel.environment%` | environment column in the panel |
| `release` | `null` | deployed version |
| `sample_rate` | `1.0` | share of error events sent |
| `traces_sample_rate` | `0.0` | share of transactions sent; `0` disables tracing |
| `send_default_pii` | `false` | attach IP address and user agent |
| `send_default_user` | `true` | attach the authenticated user |
| `ignore_exceptions` | `[]` | classes never reported |
| `ignore_transactions` | `['/_wdt', '/_profiler']` | path prefixes never measured |
| `options` | `{}` | passed straight to the SDK |

## What the bundle registers

| Listener | Event | Does |
|---|---|---|
| `ExceptionListener` | `kernel.exception` (-128) | reports the throwable the request actually died on |
| `TracingListener` | `kernel.request` / `kernel.terminate` | one transaction per main request |
| `UserListener` | `kernel.controller` | attaches the authenticated user |
| `ConsoleErrorListener` | `console.error` | reports failing commands, tagged with the command name |

The user listener registers only when `symfony/security-core` is installed.
Symfony Console is now required for the inventory command.

`404` responses are exceptions in Symfony, so add `NotFoundHttpException` to
`ignore_exceptions` unless you want them in the panel.

## Reporting by hand

The hub is available as `Dock\Ray\State\HubInterface` and as the public
`ray.hub` service:

```php
public function __construct(private HubInterface $ray) {}

public function import(): void
{
    try {
        $this->run();
    } catch (\Throwable $exception) {
        $this->ray->captureException($exception);
    }
}
```

The SDK's global functions work too — the bundle installs its hub as the
current one while the container boots.

## Messenger and long-running processes

The hub is a container singleton, so a worker keeps one client for its whole
life. Failing messages are reported through `console.error` when the worker
itself dies; for per-message reporting add your own Messenger middleware around
`captureException()`.

## Opt-in vulnerability monitoring (1.1+)

Requires `dockcodes/dock-ray` 1.4+. Reporting is disabled by default and does not
run during HTTP requests or container boot. Under the existing `ray` section in
`config/packages/ray.yaml`, add:

```yaml
ray:
    inventory:
        enabled: '%env(bool:RAY_INVENTORY)%'
        site_url: '%env(RAY_INVENTORY_SITE_URL)%'
        exclude_packages: [] # e.g. ['company/*']
```

Alongside the existing `RAY_TOKEN` and `RAY_PRIVATE_KEY`:

```dotenv
RAY_INVENTORY=true
RAY_INVENTORY_SITE_URL=https://your-app.example.com
```

The configured site URL must match the project's production or development URL.
After changing configuration, rebuild the cache and send the first report:

```shell
php bin/console cache:clear --env=prod
php bin/console ray:inventory --force --env=prod
```

The command prints a sanitized status; `--force` still respects retry backoff.
Disable reporting with `RAY_INVENTORY=false`. Inventory configuration can be
omitted entirely for existing installations using only errors and transactions.
The bundle namespace, configuration root, service aliases and existing SDK imports
remain unchanged; no namespace migration is necessary.

## Automatic hourly reporting

With Symfony Scheduler and Messenger installed, the bundle automatically registers
a schedule named `dockray_inventory`. No application scheduling code is needed:

```shell
composer require symfony/scheduler symfony/messenger
php bin/console debug:scheduler --env=prod
php bin/console messenger:consume scheduler_dockray_inventory --env=prod
```

Keep this worker running under your normal process supervisor. Installing the
Scheduler component alone does not run scheduled tasks. The hourly interval is
relative to the worker's schedule start; send the first report with `--force`.
The schedule is empty when reporting is disabled. Restart workers after changing
configuration or deploying updated dependencies.

Alternatively, without Scheduler/Messenger, run the command from cron every hour:

```cron
0 * * * * cd /var/www/your-app && php bin/console ray:inventory --env=prod >> /dev/null 2>&1
```

Choose either the dedicated worker or cron. The reporter checks every hour and
sends changed package lists on the next execution, unchanged lists every 12 hours.
After deployment run `php bin/console ray:inventory --force --env=prod` for an
immediate report. On multiple servers run inventory on one server or share its
state directory; the lock coordinates only processes using the same filesystem.

## Data scope and safety

Reports read only `composer.json`, `composer.lock` and
`vendor/composer/installed.json`. All must exist. Only installed packages in the
lock file's production `packages` list are sent, including Symfony dependencies;
`packages-dev` and the root application are excluded. The report uses the `php`
platform and DockRay's Composer vulnerability ecosystem.

Private package names are still names: exclude internal namespaces with
`inventory.exclude_packages` glob patterns such as `company/*`. Excluded packages
cannot be monitored. Payloads include only package names/versions, configured
site URL and PHP/SDK versions, never source, `.env`, configuration values,
repository URLs, descriptions, paths, credentials or user data. This does not scan
application code, operating systems or PHP extensions.

Delivery uses verified HTTPS, Bearer authentication only in headers, no redirects,
short timeouts, request/response limits, nonblocking locks and retry backoff.
The private state directory defaults to `%kernel.project_dir%/var/dockray/inventory`
and is configurable through `inventory.state_directory`. Keep it outside the
public web root, writable by the CLI/worker, persistent across deployments, with
0700 directories and 0600 files. It stores only hashes and timing information.
Invalid or missing metadata aborts reporting instead of clearing server inventory.

DockRay scans reports immediately and returns a vulnerability count. Findings
appear in the project's Software tab and existing dashboard attention indicators.
Confirmed unresolved findings notify project caretakers through configured project
channels on the existing 15-minute alert cycle, with duplicate suppression.
Reporting a fixed package version resolves the finding. The SDK does not download
advisory text or replace notification settings.

## License

MIT.
