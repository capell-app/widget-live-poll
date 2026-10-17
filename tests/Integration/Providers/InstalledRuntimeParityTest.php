<?php

declare(strict_types=1);

use Capell\Core\Actions\InstallPackageAction;
use Capell\Core\Actions\RuntimeRefresh\RefreshInstalledPackageRuntimeAction;
use Capell\Core\Data\Runtime\RuntimeRoleSelectionData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Runtime\RuntimeRoleResolver;
use Capell\Frontend\Support\Assets\FrontendResourceRegistry;
use Capell\LayoutBuilder\Support\WidgetExtensions\WidgetExtensionRegistry;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;

it('matches fresh installed boot after in-process installation and repeated refresh', function (): void {
    InstalledRuntimeParity::assertPackage('widget-live-poll');
});

it('makes the poll widget routes and maintenance schedule available once in every installed runtime role', function (string $role, bool $installed): void {
    InstalledRuntimeState::run(static function () use ($role, $installed): void {
        $app = InstalledRuntimeTestCase::boot(InstalledRuntimeParity::manifest('widget-live-poll'), $installed, static function (Application $app) use ($role): void {
            $app->instance(RuntimeRoleResolver::class, new RuntimeRoleResolver(RuntimeRoleSelectionData::fromConfiguredValue($role)));
        }, sourceRoot: dirname(__DIR__, 3));
        $catalogue = $app->make(WidgetExtensionRegistry::class);
        $resources = $app->make(FrontendResourceRegistry::class);
        $schedule = $app->make(Schedule::class);
        $router = $app->make(Router::class);
        $router->getRoutes()->refreshNameLookups();
        $events = static fn (): array => array_filter($schedule->events(), static fn (ScheduledEvent $event): bool => is_string($event->command) && str_contains($event->command, 'capell:polls:prune-voters'));
        expect($catalogue->definition('capell-app.live-poll') !== null)->toBe($installed)
            ->and($resources->get('capell-app.widget-live-poll') !== null)->toBe($installed)
            ->and($events())->toHaveCount($installed ? 1 : 0)
            ->and($router->has('capell.widget-live-poll.bootstrap'))->toBe($installed);

        $package = CapellCore::getPackage('capell-app/widget-live-poll');
        if (! $installed) {
            InstalledRuntimeTestCase::prepareLateInstallMigrations($app);
            InstallPackageAction::run($package);
        }
        RefreshInstalledPackageRuntimeAction::run($package);
        RefreshInstalledPackageRuntimeAction::run($package);
        $routes = $router->getRoutes();
        $routes->refreshNameLookups();

        expect($catalogue->definition('capell-app.live-poll')?->packageName)->toBe('capell-app/widget-live-poll')
            ->and($resources->get('capell-app.widget-live-poll')?->resources)->toHaveCount(2)
            ->and($events())->toHaveCount(1)
            ->and($routes->getByName('capell.widget-live-poll.bootstrap')?->gatherMiddleware())->toContain('throttle:capell-live-poll-bootstrap');
    });
})->with(['public', 'authoring', 'combined'])->with([true, false]);
