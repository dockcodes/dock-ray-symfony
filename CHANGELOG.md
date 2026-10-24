# Changelog

## 1.2.0

- Add the opt-in DockRay security report (`ray.security`, off by default): sha256
  digests of the application (`src/`, `config/`, `public/`, versioned by
  `ray.release`) and of every installed Composer package (versioned by its
  installed version), hashes of sensitive files (`public/index.php`, `bin/console`,
  `.env`, `.env.local`, `.env.local.php`, `config/bundles.php`,
  `public/.htaccess`, `public/.user.ini`), suspicious files in `public/uploads` and
  configured upload directories, unknown PHP files in `public/`, and posture
  (`code_writable`, `display_errors`, `exec_available`, `debug_mode`,
  `debug_tools`). Sent as platform `php` to `POST /api/v1/{token}/security`;
  never file contents, absolute paths or configuration values.
- Add `ray:security` (`--force`) with a sanitized summary of open findings, and the
  `dockray_security` Symfony Scheduler schedule (five-minute tick; reports every
  6 hours or per the server's `next_report_in`, clamped to 1–24 hours, a minute
  after a deploy, and an hour after a truncated scan).
- Hash cache by size/mtime/ctime, incremental upload scan with a weekly full pass,
  20 s / 200,000-file limits with `scan.truncated`, private state directory
  (`var/dockray/security`, 0700) and a lock.
- Measure SAPI-dependent posture on `kernel.terminate` of page views (at most
  hourly, kept in `cache.app` for up to 30 days) instead of in the console.
- Add `resources/early-fatal.php`: a dependency-free, PHP 7.4 compatible
  `auto_prepend_file` reporter for fatal errors raised before the bundle is up
  (`sdk.name` `dockray.symfony.early`).
- A backslash in an uploaded file name is masked in reported paths instead of
  being read as a directory separator, so a file named `..\x.php` cannot get the
  whole report rejected.

## 1.1.0

- Add opt-in production Composer package inventory using dockcodes/dock-ray 1.4+.
- Add ray:inventory with --force, optional hourly Symfony Scheduler/Messenger
  integration, cron instructions, package exclusions and private persistent state.
- Keep existing RayBundle, ray configuration and ray.hub alias unchanged.
- Use existing DockRay Composer findings, resolution and project notification flow.
