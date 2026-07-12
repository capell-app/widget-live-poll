<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Health;

use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\LayoutBuilder\Health\AbstractWidgetExtensionHealthCheck;
use Capell\WidgetLivePoll\Filament\LivePollWidget;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class WidgetLivePollHealthCheck extends AbstractWidgetExtensionHealthCheck
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }

    public static function runDiagnostics(): Collection
    {
        $scheduled = collect(resolve(Schedule::class)->events())->contains(static fn ($event): bool => str_contains((string) $event->command, 'capell:polls:prune-voters'));

        return parent::runDiagnostics()->push(self::result('Poll database tables', Schema::hasTable('capell_polls') && Schema::hasTable('capell_poll_options') && Schema::hasTable('capell_poll_votes')))->push(self::result('Poll public routes', Route::has('capell.widget-live-poll.bootstrap') && Route::has('capell.widget-live-poll.vote') && Route::has('capell.widget-live-poll.results')))->push(self::result('Poll voter pruning schedule', $scheduled));
    }

    protected static function definitionKey(): string
    {
        return 'capell-app.live-poll';
    }

    protected static function filamentWidget(): string
    {
        return LivePollWidget::class;
    }

    protected static function fallbackView(): string
    {
        return 'capell-widget-live-poll::widget';
    }

    protected static function resourceGroups(): array
    {
        return ['capell-app.widget-live-poll'];
    }/** @return Collection<int, DoctorCheckResultData> */
}
