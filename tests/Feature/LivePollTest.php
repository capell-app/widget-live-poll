<?php

declare(strict_types=1);
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\WidgetLivePoll\Actions\CastPollVoteAction;
use Capell\WidgetLivePoll\Actions\PruneClosedPollVotersAction;
use Capell\WidgetLivePoll\Actions\SyncPublishedPollAction;
use Capell\WidgetLivePoll\Data\LivePollRenderData;
use Capell\WidgetLivePoll\Data\LivePollRenderOptionData;
use Capell\WidgetLivePoll\Data\PollOptionInputData;
use Capell\WidgetLivePoll\Data\PollPublicationData;
use Capell\WidgetLivePoll\Health\WidgetLivePollHealthCheck;
use Capell\WidgetLivePoll\Models\PollOption;
use Capell\WidgetLivePoll\Models\PollVote;
use Capell\WidgetLivePoll\Providers\WidgetLivePollServiceProvider;
use Capell\WidgetLivePoll\Support\PollReferenceCodec;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withCookie;
use function Pest\Laravel\withCredentials;
use function Pest\Laravel\withHeader;

use Spatie\LaravelData\DataCollection;
use Symfony\Component\HttpFoundation\Cookie;

beforeEach(function (): void {
    $this->app->register(WidgetLivePollServiceProvider::class);

    foreach (glob(__DIR__ . '/../../database/migrations/*.php') ?: [] as $migrationPath) {
        $migration = require $migrationPath;
        $migration->up();
    }

    if (! Route::has('capell.widget-live-poll.bootstrap')) {
        require __DIR__ . '/../../routes/web.php';
        Route::getRoutes()->refreshNameLookups();
    }
});
/** @param list<array{id: string, label: string}> $options */
function livePollPublication(array $options): PollPublicationData
{
    return new PollPublicationData(siteId: 1, pageableType: 'page', pageableId: 10, languageId: 1, widgetInstanceId: (string) Str::uuid(), question: 'Choose one', options: new DataCollection(PollOptionInputData::class, $options));
}
it('syncs stable options and archives voted removals', function (): void {
    $first = (string) Str::uuid();
    $second = (string) Str::uuid();
    $data = livePollPublication([['id' => $first, 'label' => 'One'], ['id' => $second, 'label' => 'Two']]);
    $poll = resolve(SyncPublishedPollAction::class)->handle($data);
    $option = $poll->options()->where('stable_id', $first)->firstOrFail();
    $option->update(['votes_count' => 1]);
    $updated = new PollPublicationData(siteId: $data->siteId, pageableType: $data->pageableType, pageableId: $data->pageableId, languageId: $data->languageId, widgetInstanceId: $data->widgetInstanceId, question: 'Updated', options: new DataCollection(PollOptionInputData::class, [['id' => $second, 'label' => 'Second']]));
    resolve(SyncPublishedPollAction::class)->handle($updated);
    expect(PollOption::query()->where('stable_id', $first)->first()?->archived_at)->not->toBeNull()->and(PollOption::query()->where('stable_id', $second)->first()?->label)->toBe('Second');
});
it('counts a browser once with an atomic durable counter', function (): void {
    $optionId = (string) Str::uuid();
    $poll = resolve(SyncPublishedPollAction::class)->handle(livePollPublication([['id' => $optionId, 'label' => 'One'], ['id' => (string) Str::uuid(), 'label' => 'Two']]));
    $first = resolve(CastPollVoteAction::class)->handle($poll, $optionId, 'browser-secret');
    $duplicate = resolve(CastPollVoteAction::class)->handle($poll, $optionId, 'browser-secret');
    expect($first->accepted)->toBeTrue()->and($duplicate->accepted)->toBeFalse()->and(PollVote::query()->count())->toBe(1)->and(PollOption::query()->where('stable_id', $optionId)->value('votes_count'))->toBe(1);
});
it('prunes voter hashes after closure without deleting aggregate counts', function (): void {
    $optionId = (string) Str::uuid();
    $data = livePollPublication([['id' => $optionId, 'label' => 'One'], ['id' => (string) Str::uuid(), 'label' => 'Two']]);
    $data->closesAt = now()->subDays(91)->toAtomString();
    $poll = resolve(SyncPublishedPollAction::class)->handle($data);
    $option = $poll->options()->firstOrFail();
    PollVote::query()->create(['poll_id' => $poll->getKey(), 'poll_option_id' => $option->getKey(), 'voter_hash' => str_repeat('a', 64), 'created_at' => now()->subDays(91)]);
    $option->increment('votes_count');
    expect(resolve(PruneClosedPollVotersAction::class)->handle())->toBe(1)->and(PollVote::query()->count())->toBe(0)->and($option->fresh()?->votes_count)->toBe(1);
});

it('reports definition authoring view assets tables routes and pruning as healthy', function (): void {
    $diagnostics = WidgetLivePollHealthCheck::runDiagnostics();

    expect($diagnostics)->toHaveCount(7)
        ->and($diagnostics->where('passed', false)->pluck('label')->all())->toBe([])
        ->and(WidgetLivePollHealthCheck::passed())->toBeTrue();
});

it('renders an escaped static fallback without stable identities or package metadata', function (): void {
    $renderData = new LivePollRenderData(
        question: '<script>Choose</script>',
        options: new DataCollection(LivePollRenderOptionData::class, [
            new LivePollRenderOptionData((string) Str::uuid(), '<img src=x>'),
            new LivePollRenderOptionData((string) Str::uuid(), 'Second'),
        ]),
    );

    $html = view('capell-widget-live-poll::widget', compact('renderData'))->render();

    expect($html)->toContain('&lt;script&gt;Choose&lt;/script&gt;')
        ->toContain('&lt;img src=x&gt;')
        ->not->toContain('capell-app.live-poll')
        ->not->toContain('instance_id');
});

it('bootstraps a host-only voter cookie and serves isolated aggregate etags', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->language($language)->create();
    SiteDomain::factory()->site($site)->language($language)->create([
        'domain' => 'poll.test',
        'scheme' => 'http',
        'path' => null,
        'status' => true,
    ]);
    $optionId = (string) Str::uuid();
    $publication = new PollPublicationData(
        siteId: $site->id,
        pageableType: 'page',
        pageableId: 20,
        languageId: $language->id,
        widgetInstanceId: (string) Str::uuid(),
        question: 'Choose',
        options: new DataCollection(PollOptionInputData::class, [
            ['id' => $optionId, 'label' => 'One'],
            ['id' => (string) Str::uuid(), 'label' => 'Two'],
        ]),
    );
    $poll = resolve(SyncPublishedPollAction::class)->handle($publication);
    $reference = resolve(PollReferenceCodec::class)->encode($poll);

    $bootstrap = getJson('http://poll.test/_capell/widget-polls/' . $reference . '/bootstrap')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonStructure(['csrf_token', 'has_voted', 'open', 'results']);
    $cookie = collect($bootstrap->baseResponse->headers->getCookies())
        ->first(static fn (Cookie $candidate): bool => $candidate->getName() === 'capell_poll_voter');

    expect($cookie)->not->toBeNull()
        ->and($cookie?->isHttpOnly())->toBeTrue()
        ->and($cookie?->getSameSite())->toBe('lax')
        ->and($cookie?->getDomain())->toBeNull();

    throw_unless($cookie instanceof Cookie);

    $csrfToken = $bootstrap->json('csrf_token');
    expect($csrfToken)->toBeString();
    throw_unless(is_string($csrfToken));
    $cookieValue = $cookie->getValue();
    throw_unless(is_string($cookieValue));
    $rawVoterCookie = CookieValuePrefix::remove(Crypt::decryptString($cookieValue));
    withCredentials();
    withCookie('capell_poll_voter', $rawVoterCookie);
    withHeader('X-CSRF-TOKEN', $csrfToken);
    $vote = postJson('http://poll.test/_capell/widget-polls/' . $reference . '/vote', ['option_id' => $optionId])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJson(['accepted' => true, 'hasVoted' => true]);
    withCredentials();
    withCookie('capell_poll_voter', $rawVoterCookie);
    withHeader('X-CSRF-TOKEN', $csrfToken);
    postJson('http://poll.test/_capell/widget-polls/' . $reference . '/vote', ['option_id' => $optionId])
        ->assertOk()
        ->assertJson(['accepted' => false, 'hasVoted' => true]);

    $results = getJson('http://poll.test/_capell/widget-polls/' . $reference . '/results')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=30, public')
        ->assertHeader('ETag');
    $etag = $results->baseResponse->headers->get('ETag');
    throw_unless(is_string($etag));
    withHeader('If-None-Match', $etag);
    getJson('http://poll.test/_capell/widget-polls/' . $reference . '/results')
        ->assertStatus(304);
    getJson('http://other.test/_capell/widget-polls/' . $reference . '/bootstrap')
        ->assertNotFound();
    expect($vote->json('results.' . $optionId))->toBe(1);
});
