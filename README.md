# Live Poll Widget

<!-- prettier-ignore-start -->

## What This Plugin Adds

Live Poll Widget is an **Available**, **Schema-owning** Capell package in the **Capell Foundation** product group. It ships as `capell-app/widget-live-poll` and extends these surfaces: admin, frontend, console.

Locale-specific single-choice polls with stable options, transactional vote counters, privacy-safe voter identity, and aggregate caching.

After install, admins get package-owned management surfaces and public users may see package-owned frontend output or routes.

Status details:

- Status: Available
- Tier: free
- Bundle: foundation
- Composer package: `capell-app/widget-live-poll`
- Namespace: `Capell\WidgetLivePoll`
- Theme key: not applicable

## Why It Matters

**For developers:** The package gives developers package-owned service providers, Actions, Data objects, models, Laravel routes, Filament classes, and Blade views instead of pushing this behaviour into core or application code.

**For teams:** Transactional live polls with privacy-safe one-vote browser identity.

## Screens And Workflow

Marketplace media is declared in `capell.json`:

- Live Poll widget.

## Technical Shape

- Service providers: `Capell\WidgetLivePoll\Providers\WidgetLivePollServiceProvider`.
- Config files: `packages/widget-live-poll/config/capell-widget-live-poll.php`.
- Migrations: `packages/widget-live-poll/database/migrations/2026_07_09_000001_create_capell_polls_table.php`, `packages/widget-live-poll/database/migrations/2026_07_09_000002_create_capell_poll_options_table.php`, `packages/widget-live-poll/database/migrations/2026_07_09_000003_create_capell_poll_votes_table.php`.
- Models: `Poll`, `PollOption`, `PollVote`.
- Filament classes: `LivePollWidget`.
- Route files: `packages/widget-live-poll/routes/web.php`.
- Listeners: `SyncPublishedPollsListener`.
- Actions: `CastPollVoteAction`, `PruneClosedPollVotersAction`, `ResolveLivePollPayloadsAction`, `SyncPublishedPollAction`.
- Data objects: `LivePollInputData`, `LivePollRenderData`, `LivePollRenderOptionData`, `PollOptionInputData`, `PollPublicationData`, `PollVoteResultData`.
- Console command classes: `PrunePollVotersCommand`.
- Manifest contributions: `content-widget: Capell\WidgetLivePoll\WidgetLivePollContribution`, `scheduled-job: Capell\WidgetLivePoll\Manifest\LivePollPruneScheduleContribution`.
- Health checks: `Capell\WidgetLivePoll\Health\WidgetLivePollHealthCheck`.
- Blade views: `packages/widget-live-poll/resources/views/widget.blade.php`.
- Cache tags: `widget-live-poll`.

## Data Model

- Required tables: `capell_polls`, `capell_poll_options`, `capell_poll_votes`.
- Models: `Poll`, `PollOption`, `PollVote`.
- Migration files: `2026_07_09_000001_create_capell_polls_table.php`, `2026_07_09_000002_create_capell_poll_options_table.php`, `2026_07_09_000003_create_capell_poll_votes_table.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: Docs gap unless the package has an explicit pruning command, retention setting, or tested cascade path.

## Install Impact

- Admin navigation: adds package-owned Filament classes when registered.
- Permissions: none declared in `capell.json`.
- Public routes: route files exist and must be reviewed before public enablement.
- Database changes: package migrations are declared.
- Settings: no package settings declared.
- Queues or schedules: none detected in standard package paths.
- Cache tags: `widget-live-poll`.
- Commands: console command classes detected: `PrunePollVotersCommand`.

## Common Pitfalls

- Run migrations before opening package resources or public routes.
- Review route middleware, throttling, signed URLs, and public-output safety before exposing routes.
- Keep public Blade and cached HTML free of authoring markers, model IDs, permissions, signed editor URLs, and lazy database queries.
- Run package commands from the host app; in this repository use `vendor/bin/pest` for package tests.
- Keep `composer.json`, `composer.local.json`, `capell.json`, docs, screenshots, and tests aligned when the package surface changes.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Route returns unexpected output | Route cache, middleware, or signed URL setup does not match the package route file | Check the route files listed in `Technical Shape` | Clear route cache and verify middleware before exposing public routes |
| Public output leaks unexpected state | Render data, cache variation, or authoring boundary has regressed | Check public Blade, cache tags, and public-output safety tests | Move data loading out of Blade and rerun the package public-output tests |

## Quick Start

1. Install the package: `composer require capell-app/widget-live-poll`.
2. Run the required setup: `php artisan migrate`.
3. Open the related Capell admin surface and verify Live Poll Widget appears.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [Layout Builder](../layout-builder/README.md).
- Focused tests: `vendor/bin/pest packages/widget-live-poll/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
