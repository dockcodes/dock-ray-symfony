# Changelog

## Unreleased — intended 1.1.0

- Add opt-in production Composer package inventory using dockcodes/dock-ray 1.4+.
- Add ray:inventory with --force, optional hourly Symfony Scheduler/Messenger
  integration, cron instructions, package exclusions and private persistent state.
- Keep existing RayBundle, ray configuration and ray.hub alias unchanged.
- Use existing DockRay Composer findings, resolution and project notification flow.
