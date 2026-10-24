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
| `SecurityPostureListener` | `kernel.terminate` (-8192) | with the security report enabled: remembers PHP settings of a page view, at most hourly |

The user listener registers only when `symfony/security-core` is installed.
Symfony Console is now required for the `ray:inventory` and `ray:security` commands.

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

## Opt-in security report (1.2+)

Off by default. Under the existing `ray` section, alongside your project credentials:

```yaml
ray:
    release: '%env(default::RAY_RELEASE)%'   # optional; needed for the application group
    security:
        enabled: '%env(bool:RAY_SECURITY)%'
        site_url: '%env(default::RAY_SECURITY_SITE_URL)%' # optional; defaults to inventory.site_url
        upload_directories: []                   # e.g. ['public/media/uploads']; public/uploads is always scanned
```

```dotenv
RAY_SECURITY=true
```

The configuration block is optional; the bundle supplies the defaults when it is
missing. Rebuild the cache after changing it.

### Scheduling

With Symfony Scheduler and Messenger installed, the bundle registers a schedule named
`dockray_security` that ticks every five minutes. The tick is cheap: the reporter
decides whether a report is due — every 6 hours (or the interval DockRay answers with,
clamped to 1–24 hours), on the first tick at least a minute after a deploy changed
`composer.lock` or the release, and an hour after a scan that stopped at its limit.
Consume it with your normal process supervisor, alone or in one worker with the
inventory schedule:

```shell
php bin/console messenger:consume scheduler_dockray_security scheduler_dockray_inventory --env=prod
```

The schedule is empty when the report is disabled. Without Scheduler/Messenger, run
the command from cron instead (choose one, not both):

```cron
*/5 * * * * cd /var/www/your-app && php bin/console ray:security --env=prod >> /dev/null 2>&1
```

Nothing is scanned or sent while serving HTTP pages or booting the kernel. To report
now, or to see the open findings:

```shell
php bin/console ray:security --force --env=prod
```

`--force` still respects retry backoff. The command prints a sanitized status, the
number of open findings per severity and the findings DockRay returned; everything
from the server is stripped of control characters and escaped before printing.

### What the report contains

`POST {url}/api/v1/{token}/security`, platform `php` (DockRay has no separate Symfony
platform; the inventory reports the same way):

| Section | Content |
|---|---|
| `groups` | `app/app`: sha256 digest of the PHP/JS/`.htaccess` files in `src/`, `config/` and `public/` (without `public/bundles/` and the upload directories), versioned by `ray.release` and sent only when it is set. `package/{name}`: the same for every Composer package installed in `vendor/`, versioned by its installed version (a branch such as `dev-main` by its commit). `vendor/composer/`, `vendor/autoload.php` and `vendor/bin/` belong to no package and are left out — every `composer dump-autoload` rewrites them; `var/` is never read |
| `files` | sha256 and size of `public/index.php`, `bin/console`, `.env`, `.env.local`, `.env.local.php`, `config/bundles.php`, `public/.htaccess`, `public/.user.ini` |
| `suspicious` | PHP files in `public/uploads` and the configured upload directories, files there that start with `<?php` under another extension, `.htaccess`/`.user.ini` there that make the server run PHP (`php_handler`), hidden directories there, PHP files in `public/` other than `index.php`, and backdoor indicator *names* found in them |
| `posture` | `code_writable`, `display_errors`, `exec_available` (see below), `debug_mode` (`kernel.debug`), `debug_tools` (WebProfilerBundle or DebugBundle registered) |

It never contains file contents, absolute paths, users or configuration values —
hashing `.env` tells DockRay when the file changed and nothing else. DockRay records
the first report as the baseline; afterwards a changed digest without a version
change, a changed sensitive file or a suspicious file becomes a finding.

`debug_mode` and `debug_tools` describe the kernel the report runs in, so run the
worker or cron with the environment visitors get (`--env=prod`, same `APP_DEBUG`).
`code_writable`, `display_errors` and `exec_available` depend on the PHP SAPI, its
`php.ini` and the process user, so the console cannot measure them. With the report
enabled, the bundle measures them on `kernel.terminate` of a page view (not XHR,
JSON or `/api` requests), at most once an hour, and keeps them in the `cache.app`
pool; the report uses them while they are younger than 30 days and otherwise leaves
the three keys out, so DockRay keeps its earlier verdict. The default filesystem pool
under `var/cache` works when the web server and the worker share it; `cache:clear`
empties it, and the next page view measures again. `display_errors` is the value PHP
started the request with — the Symfony runtime switches the setting once it boots, so
that value decides whether an error before that reaches visitors.

Hashing is expensive, so per-file hashes are cached by size, mtime and ctime. The
upload directories are walked on every run, but file contents are read only for files
whose ctime is at or after the start of the previous run, plus a weekly full pass
that gets ten seconds per run and continues from a cursor; earlier findings are
carried over for files whose ctime has not changed, so every report is complete.
A walk that reaches 300,000 entries, or a scan that reaches 20 seconds or 200,000
files, is marked truncated: only groups hashed completely are sent, DockRay keeps
the findings it could not re-check, and the next run continues from the cache.

State and cache live in `security.state_directory` (default
`%kernel.project_dir%/var/dockray/security`): keep it persistent across deployments,
outside the web root and writable by the worker, with 0700 directories and 0600
files. It stores only hashes, sizes, timestamps and the last result. On multiple
servers run the report on one server or share that directory; the lock coordinates
only processes using the same filesystem.

Delivery uses verified HTTPS only, Bearer authentication in the header, no redirects,
a 5 s connect and 20 s total timeout, a 2 MB body limit, retry backoff and a lock.

## Early fatal errors

The bundle reports errors once the kernel handles a request. A fatal error before
that — a parse error in `config/bundles.php` or `src/Kernel.php`, a broken file in
`vendor/`, memory exhausted while the container compiles — never reaches it. For
those, the bundle ships a dependency-free file (PHP 7.4+) to load with
`auto_prepend_file`, in `php.ini`, `public/.user.ini` or the PHP-FPM pool:

```ini
auto_prepend_file=/absolute/path/to/app/vendor/dockcodes/dock-ray-symfony/resources/early-fatal.php
```

It only registers a shutdown function. On a fatal error (`E_ERROR`, `E_PARSE`,
`E_CORE_ERROR`, `E_COMPILE_ERROR`, `E_USER_ERROR`, `E_RECOVERABLE_ERROR`) it waits
until every other shutdown function ran — Symfony's error page, the SDK's own handler
— and sends one event to the regular ingest endpoint with `sdk.name`
`dockray.symfony.early`, unless a DockRay SDK client with its error listeners is
already active (then the bundle reported the error itself). It sends at most one
report per distinct error every ten seconds, never prints anything and never throws.

Credentials come from `RAY_TOKEN`, `RAY_PRIVATE_KEY`, `RAY_URL` (default
`https://dockray.io`), `RAY_ENVIRONMENT` (falls back to `APP_ENV`, then `prod`) and
`RAY_RELEASE`. Values Symfony's Dotenv loaded from `.env` or `.env.local.php` are
used when the error happened after that; to cover the earliest errors set the
variables in the server environment (PHP-FPM `env[RAY_TOKEN]`, the container
environment). Without credentials the file does nothing. It sends over HTTPS only
(plain HTTP only to `localhost`), with a 2 s connect and 3 s total timeout, after the
response was flushed to the visitor.

## License

MIT.
