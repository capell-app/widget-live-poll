<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Providers;

use Capell\Core\Enums\PresentationLoadingStrategy;
use Capell\Core\Events\PageDeleted;
use Capell\Core\Events\PageSaved;
use Capell\LayoutBuilder\Data\WidgetExtensions\WidgetExtensionCapabilitiesData;
use Capell\LayoutBuilder\Data\WidgetExtensions\WidgetExtensionDefinitionData;
use Capell\LayoutBuilder\Enums\WidgetPresentationCapability;
use Capell\LayoutBuilder\Providers\AbstractFoundationWidgetServiceProvider;
use Capell\WidgetLivePoll\Actions\ResolveLivePollPayloadsAction;
use Capell\WidgetLivePoll\Console\Commands\PrunePollVotersCommand;
use Capell\WidgetLivePoll\Data\LivePollInputData;
use Capell\WidgetLivePoll\Data\LivePollRenderData;
use Capell\WidgetLivePoll\Filament\LivePollWidget;
use Capell\WidgetLivePoll\Listeners\SyncPublishedPollsListener;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Override;
use ReflectionProperty;
use Spatie\LaravelPackageTools\Package;

final class WidgetLivePollServiceProvider extends AbstractFoundationWidgetServiceProvider
{
    public static string $name = 'capell-widget-live-poll';

    public static string $packageName = 'capell-app/widget-live-poll';

    #[Override]
    public function configurePackage(Package $package): void
    {
        parent::configurePackage($package);
        $package->hasConfigFile('capell-widget-live-poll')->hasMigrations([
            '2026_07_09_000001_create_capell_polls_table',
            '2026_07_09_000002_create_capell_poll_options_table',
            '2026_07_09_000003_create_capell_poll_votes_table',
            '2026_10_04_000001_remove_implicit_timestamp_updates_from_widget_live_poll',
        ])->hasCommand(PrunePollVotersCommand::class);
    }

    #[Override]
    public function packageBooted(): void {}

    #[Override]
    protected static function resourceGroup(): string
    {
        return 'capell-app.widget-live-poll';
    }

    #[Override]
    protected function bootInstalledRuntime(): void
    {
        // Poll routes must not inherit a dependent package's active route group.
        $router = $this->app->make(Router::class);
        $groups = new ReflectionProperty(Router::class, 'groupStack');
        $previous = $groups->getValue($router);
        $groups->setValue($router, []);
        try {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        } finally {
            $groups->setValue($router, $previous);
        }

        $this->registerWidget();
        $this->registerRateLimits();
        Event::listen(PageSaved::class, [SyncPublishedPollsListener::class, 'handleSaved']);
        Event::listen(PageDeleted::class, [SyncPublishedPollsListener::class, 'handleDeleted']);
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('capell:polls:prune-voters')->daily()->withoutOverlapping();
        });
    }

    #[Override]
    protected function definition(): WidgetExtensionDefinitionData
    {
        return new WidgetExtensionDefinitionData(key: 'capell-app.live-poll', packageName: self::$packageName, stateVersion: 1, filamentWidget: LivePollWidget::class, inputData: LivePollInputData::class, renderData: LivePollRenderData::class, fallbackView: 'capell-widget-live-poll::widget', components: ['blade' => 'capell::widgets.capell-app.live-poll'], resourceGroups: [self::resourceGroup()], defaultResourceLoadingStrategy: PresentationLoadingStrategy::Visible, capabilities: new WidgetExtensionCapabilitiesData(requiresInstanceIdentity: true, presentationCapabilities: [WidgetPresentationCapability::Width, WidgetPresentationCapability::Alignment, WidgetPresentationCapability::LoadingStrategy]), batchPayloadResolver: ResolveLivePollPayloadsAction::class);
    }

    private function registerRateLimits(): void
    {
        RateLimiter::for('capell-live-poll-bootstrap', static fn (Request $request): array => [Limit::perMinute(20)->by('bootstrap:' . $request->ip()), Limit::perMinute(5)->by('bootstrap:' . $request->ip() . ':' . hash('sha256', (string) $request->route('reference')))]);
        RateLimiter::for('capell-live-poll-vote', static fn (Request $request): array => [Limit::perMinute(20)->by('vote:' . $request->ip()), Limit::perMinute(5)->by('vote:' . $request->ip() . ':' . hash('sha256', (string) $request->route('reference')))]);
        RateLimiter::for('capell-live-poll-results', static fn (Request $request): Limit => Limit::perMinute(60)->by('results:' . $request->ip()));
    }
}
