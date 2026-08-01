# ADR: Deployment and installer

- Status: Accepted
- Baseline: AssestMe specification 2.7
- Source commit: `d466ff1c0c3851fecee3d468cde7cb9359db5ae4`

## Context

Defines the supported prebuilt release/hosting model, operator scheduler setup, bounded installer implementation, and automatic capability detection.

## Decisions

| ID | Status | Decision |
|---|---|---|
| D-019 (v1) | SUPERSEDED | Nginx + PHP-FPM was the production profile and direct Artisan startup was the local development profile; superseded by D-019 on 2026-07-18 |
| D-019 | SUPERSEDED | Docker Compose development and a future, not-yet-implemented CloudPanel production profile were approved; superseded by D-064 on 2026-07-31 |
| D-064 (v1) | SUPERSEDED | CloudPanel was the sole approved production destination; superseded by D-064 on 2026-08-01 |
| D-064 | APPROVED | The prebuilt release ZIP and application-specific web installer support CloudPanel and traditional PHP hosting that provide the verified runtime capabilities, a `public` document root, private writable storage, and manual scheduler configuration; Composer and Node.js are not required on the production server |
| D-068 | APPROVED | Scheduler setup remains an operator action in CloudPanel or the hosting control panel/crontab; AssestMe emits an atomic every-minute heartbeat, reports its freshness, and shows the exact detected PHP CLI plus Artisan command without attempting to configure cron |
| D-069 | APPROVED | `eii/laravel-installer` is excluded as an authoritative runtime dependency; AssestMe implements only its bounded installation flow with Laravel routes, controllers, form requests, Blade, file sessions, application services, local static CSS, and Symfony Process |
| D-070 | APPROVED | The installer fixes `APP_NAME` to `AssestMe`, detects PHP CLI and mandatory WeasyPrint automatically with simple deterministic real probes, never installs packages, and exposes only optional advanced environment overrides for non-standard paths |

## Consequences

- The listed decisions remain normative with their recorded status.
- `SUPERSEDED` entries remain historical evidence and must not be reactivated implicitly.
- Detailed application contracts live in the reference pages linked from [the documentation index](../index.md); those pages may clarify implementation but may not contradict these decisions.
- A future change requires explicit approval and a recorded superseding decision, not a silent edit.
