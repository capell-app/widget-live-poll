# Live Poll Widget reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/widget-live-poll`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Live Poll Widget is an **Available** Capell package in the **Capell Essentials** product group. It ships as `capell-app/widget-live-poll` and extends these surfaces: admin, frontend, console.

- Status: Available
- Tier: free
- Bundle: foundation
- Composer package: `capell-app/widget-live-poll`
- Namespace: `Capell\WidgetLivePoll`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\WidgetLivePoll\Providers\MigrationMetadataServiceProvider`
- `Capell\WidgetLivePoll\Providers\WidgetLivePollServiceProvider`

### Config files

- `packages/widget-live-poll/config/capell-widget-live-poll.php`

### Migrations

- `packages/widget-live-poll/database/migrations/2026_07_09_000001_create_capell_polls_table.php`
- `packages/widget-live-poll/database/migrations/2026_07_09_000002_create_capell_poll_options_table.php`
- `packages/widget-live-poll/database/migrations/2026_07_09_000003_create_capell_poll_votes_table.php`
- `packages/widget-live-poll/database/migrations/2026_10_04_000001_remove_implicit_timestamp_updates_from_widget_live_poll.php`

### Models

- `Poll`
- `PollOption`
- `PollVote`

### Filament classes

- `LivePollWidget`

### Route files

- `packages/widget-live-poll/routes/web.php`

### Listeners

- `SyncPublishedPollsListener`

### Actions

- `CastPollVoteAction`
- `LimitDailyVotesPerAddressAction`
- `PruneClosedPollVotersAction`
- `ResolveLivePollPayloadsAction`
- `SyncPublishedPollAction`

### Data objects

- `LivePollInputData`
- `LivePollRenderData`
- `LivePollRenderOptionData`
- `PollOptionInputData`
- `PollPublicationData`
- `PollVoteResultData`

### Scheduled commands

- `capell:polls:prune-voters (daily; package registered)`

### Console command classes

- `PrunePollVotersCommand`

### Manifest contributions

- `asset: Capell\WidgetLivePoll\WidgetLivePollContribution`
- `content-widget: Capell\WidgetLivePoll\WidgetLivePollContribution`
- `migration: Capell\WidgetLivePoll\Manifest\WidgetLivePollMigrationsContribution`
- `scheduled-job: Capell\WidgetLivePoll\Manifest\LivePollPruneScheduleContribution`

### Health checks

- `Capell\WidgetLivePoll\Health\WidgetLivePollHealthCheck`

### Blade views

- `packages/widget-live-poll/resources/views/widget.blade.php`

### Cache tags

- `widget-live-poll`


## Data Model

- Required tables: `capell_polls`, `capell_poll_options`, `capell_poll_votes`.
- Models: `Poll`, `PollOption`, `PollVote`.
- Core record references in migrations: `sites via site_id`, `languages via language_id`.
- Migration files: `2026_07_09_000001_create_capell_polls_table.php`, `2026_07_09_000002_create_capell_poll_options_table.php`, `2026_07_09_000003_create_capell_poll_votes_table.php`, `2026_10_04_000001_remove_implicit_timestamp_updates_from_widget_live_poll.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare cascade-on-delete relationships; retention is scheduled through `capell:polls:prune-voters` (daily; registered by the package provider).

<!-- prettier-ignore-end -->
