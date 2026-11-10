<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Providers;

use Capell\Core\Support\Migration\MigrationRootRegistry;
use Illuminate\Support\ServiceProvider;
use Override;

final class MigrationMetadataServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {

        $this->callAfterResolving(MigrationRootRegistry::class, static function (MigrationRootRegistry $registry): void {
            $registry->register('capell-app/widget-live-poll', __DIR__ . '/../../database/migrations', [
                '2026_07_09_000001_create_capell_polls_table' => 'capell_polls',
                '2026_07_09_000002_create_capell_poll_options_table' => 'capell_poll_options',
                '2026_07_09_000003_create_capell_poll_votes_table' => 'capell_poll_votes',
            ]);
        });
    }
}
