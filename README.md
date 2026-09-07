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
    url: '%env(default::RAY_URL)%'
    traces_sample_rate: 0.2
    ignore_exceptions:
        - Symfony\Component\HttpKernel\Exception\NotFoundHttpException
```

Every key is optional. Without a token and a private key the bundle loads but
sends nothing.

| Key | Default | Meaning |
|---|---|---|
| `url` | `https://dockray.io` | DockRay instance |
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

The last two register only when `symfony/security-core` and
`symfony/console` are installed.

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

## License

MIT.
