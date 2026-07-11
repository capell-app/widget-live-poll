<?php

declare(strict_types=1);
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\LayoutBuilder\Data\WidgetExtensions\WidgetExtensionPayloadBatchData;
use Capell\LayoutBuilder\Data\WidgetExtensions\WidgetExtensionPayloadInputData;
use Capell\LayoutBuilder\Data\WidgetExtensions\WidgetExtensionRenderContextData;
use Capell\WidgetLivePoll\Actions\CastPollVoteAction;
use Capell\WidgetLivePoll\Actions\PruneClosedPollVotersAction;
use Capell\WidgetLivePoll\Actions\ResolveLivePollPayloadsAction;
use Capell\WidgetLivePoll\Actions\SyncPublishedPollAction;
use Capell\WidgetLivePoll\Data\LivePollInputData;
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

it('rechecks mutable poll state inside the vote transaction', function (): void {
    $optionId = (string) Str::uuid();
    $poll = resolve(SyncPublishedPollAction::class)->handle(livePollPublication([
        ['id' => $optionId, 'label' => 'One'],
        ['id' => (string) Str::uuid(), 'label' => 'Two'],
    ]));

    $poll->newQuery()->whereKey($poll->getKey())->update(['closes_at' => now()->subSecond()]);

    expect(fn () => resolve(CastPollVoteAction::class)->handle($poll, $optionId, 'late-browser'))
        ->toThrow(RuntimeException::class, 'Poll is not open.')
        ->and(PollVote::query()->count())->toBe(0)
        ->and(PollOption::query()->where('stable_id', $optionId)->value('votes_count'))->toBe(0);
});

it('applies each results visibility policy without leaking after-close totals to voters', function (): void {
    $optionId = (string) Str::uuid();
    $publication = livePollPublication([
        ['id' => $optionId, 'label' => 'One'],
        ['id' => (string) Str::uuid(), 'label' => 'Two'],
    ]);
    $publication->resultsVisibility = 'after_close';
    $publication->closesAt = now()->addHour()->toAtomString();
    $poll = resolve(SyncPublishedPollAction::class)->handle($publication);

    $vote = resolve(CastPollVoteAction::class)->handle($poll, $optionId, 'browser-secret');

    expect($vote->accepted)->toBeTrue()
        ->and($vote->results)->toBeNull()
        ->and(resolve(CastPollVoteAction::class)->resultsForViewer($poll, true))->toBeNull();

    $poll->update(['opens_at' => now()->addHour(), 'closes_at' => now()->addHours(2)]);
    $poll->refresh();
    expect(resolve(CastPollVoteAction::class)->resultsForViewer($poll, true))->toBeNull();

    $poll->update(['opens_at' => null, 'closes_at' => now()->subSecond()]);
    $poll->refresh();
    expect(resolve(CastPollVoteAction::class)->resultsForViewer($poll, false))->toMatchArray([$optionId => 1]);

    $poll->update(['results_visibility' => 'invalid']);
    $poll->refresh();
    expect(resolve(CastPollVoteAction::class)->resultsForViewer($poll, true))->toBeNull();
});

it('removes private totals from shared render payloads', function (): void {
    $optionId = (string) Str::uuid();
    $publication = livePollPublication([
        ['id' => $optionId, 'label' => 'One'],
        ['id' => (string) Str::uuid(), 'label' => 'Two'],
    ]);
    $poll = resolve(SyncPublishedPollAction::class)->handle($publication);
    $poll->options()->where('stable_id', $optionId)->increment('votes_count', 9);
    $input = new LivePollInputData($publication->question, $publication->options, resultsVisibility: 'after_vote');
    $batch = new WidgetExtensionPayloadBatchData(
        [new WidgetExtensionPayloadInputData($publication->widgetInstanceId, $input)],
        new WidgetExtensionRenderContextData(null),
    );

    $privatePayload = resolve(ResolveLivePollPayloadsAction::class)->resolve($batch)[$publication->widgetInstanceId];

    expect($privatePayload->showResults)->toBeFalse()
        ->and($privatePayload->options->toCollection()->pluck('votesCount')->all())->toBe([0, 0]);

    $poll->update(['results_visibility' => 'always']);
    $publicPayload = resolve(ResolveLivePollPayloadsAction::class)->resolve($batch)[$publication->widgetInstanceId];

    expect($publicPayload->showResults)->toBeTrue()
        ->and($publicPayload->options->toCollection()->pluck('votesCount')->all())->toBe([9, 0]);
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

it('renders an escaped static fallback without stable identities or hidden counts', function (): void {
    $renderData = new LivePollRenderData(
        question: '<script>Choose</script>',
        options: new DataCollection(LivePollRenderOptionData::class, [
            new LivePollRenderOptionData((string) Str::uuid(), '<img src=x>', 42),
            new LivePollRenderOptionData((string) Str::uuid(), 'Second', 7),
        ]),
    );

    $html = view('capell-widget-live-poll::widget', compact('renderData'))->render();

    expect($html)->toContain('&lt;script&gt;Choose&lt;/script&gt;')
        ->toContain('&lt;img src=x&gt;')
        ->not->toContain('capell-app.live-poll')
        ->not->toContain('>42<')
        ->not->toContain('>7<');
});

it('renders public totals only when results are visible to everyone', function (): void {
    $visibleRenderData = new LivePollRenderData(
        question: 'Choose',
        options: new DataCollection(LivePollRenderOptionData::class, [
            new LivePollRenderOptionData((string) Str::uuid(), 'One', 12),
            new LivePollRenderOptionData((string) Str::uuid(), 'Two', 3),
        ]),
        showResults: true,
    );

    $visibleHtml = view('capell-widget-live-poll::widget', ['renderData' => $visibleRenderData])->render();

    expect($visibleHtml)->toMatch('/<strong>\s*12\s*<\/strong>/')
        ->toMatch('/<strong>\s*3\s*<\/strong>/');
});

it('bootstraps a host-only voter cookie and keeps results private until they are visible', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->language($language)->create();
    SiteDomain::factory()->site($site)->language($language)->create([
        'domain' => 'poll.test',
        'scheme' => 'http',
        'path' => null,
        'status' => true,
    ]);
    SiteDomain::factory()->site(Site::factory()->language($language)->create())->language($language)->create([
        'domain' => 'other.test',
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
        ->assertJson(['has_voted' => false, 'open' => true, 'results' => null])
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

    withCredentials();
    withCookie('capell_poll_voter', $rawVoterCookie);
    getJson('http://poll.test/_capell/widget-polls/' . $reference . '/bootstrap')
        ->assertOk()
        ->assertJson(['has_voted' => true, 'results' => [$optionId => 1]]);

    $results = withCookie('capell_poll_voter', 'other-browser-secret')->getJson('http://poll.test/_capell/widget-polls/' . $reference . '/results')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJson(['results' => null]);
    withCookie('capell_poll_voter', $rawVoterCookie);
    getJson('http://poll.test/_capell/widget-polls/' . $reference . '/results')
        ->assertOk()
        ->assertJson(['results' => [$optionId => 1]]);
    getJson('http://other.test/_capell/widget-polls/' . $reference . '/bootstrap')
        ->assertNotFound();
    getJson('http://poll.test/_capell/widget-polls/not-a-reference/bootstrap')
        ->assertNotFound();
    postJson('http://poll.test/_capell/widget-polls/not-a-reference/vote', ['option_id' => $optionId])
        ->assertNotFound();
    getJson('http://poll.test/_capell/widget-polls/not-a-reference/results')
        ->assertNotFound();
    $otherPoll = resolve(SyncPublishedPollAction::class)->handle(new PollPublicationData(
        siteId: $site->id,
        pageableType: 'page',
        pageableId: 21,
        languageId: $language->id,
        widgetInstanceId: (string) Str::uuid(),
        question: 'Choose later',
        options: new DataCollection(PollOptionInputData::class, [
            ['id' => (string) Str::uuid(), 'label' => 'One'],
            ['id' => (string) Str::uuid(), 'label' => 'Two'],
        ]),
        closesAt: now()->addHour()->toAtomString(),
        resultsVisibility: 'after_close',
    ));
    $otherReference = resolve(PollReferenceCodec::class)->encode($otherPoll);
    getJson('http://other.test/_capell/widget-polls/' . $otherReference . '/bootstrap')
        ->assertNotFound();
    postJson('http://other.test/_capell/widget-polls/' . $otherReference . '/vote', ['option_id' => $optionId])
        ->assertNotFound();
    getJson('http://other.test/_capell/widget-polls/' . $otherReference . '/results')
        ->assertNotFound();
    expect($vote->json('results.' . $optionId))->toBe(1);
});

it('shows after-close results only after the poll closes', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->language($language)->create();
    SiteDomain::factory()->site($site)->language($language)->create([
        'domain' => 'poll.test',
        'scheme' => 'http',
        'path' => null,
        'status' => true,
    ]);
    $optionId = (string) Str::uuid();
    $poll = resolve(SyncPublishedPollAction::class)->handle(new PollPublicationData(
        siteId: $site->id,
        pageableType: 'page',
        pageableId: 30,
        languageId: $language->id,
        widgetInstanceId: (string) Str::uuid(),
        question: 'Choose',
        options: new DataCollection(PollOptionInputData::class, [
            ['id' => $optionId, 'label' => 'One'],
            ['id' => (string) Str::uuid(), 'label' => 'Two'],
        ]),
        closesAt: now()->addHour()->toAtomString(),
        resultsVisibility: 'after_close',
    ));
    $reference = resolve(PollReferenceCodec::class)->encode($poll);

    getJson('http://poll.test/_capell/widget-polls/' . $reference . '/bootstrap')
        ->assertOk()
        ->assertJson(['results' => null]);
    getJson('http://poll.test/_capell/widget-polls/' . $reference . '/results')
        ->assertOk()
        ->assertJson(['results' => null]);

    $poll->update(['closes_at' => now()->subMinute()]);
    $poll->refresh();

    getJson('http://poll.test/_capell/widget-polls/' . $reference . '/bootstrap')
        ->assertOk()
        ->assertJson(['results' => [$optionId => 0]]);
    getJson('http://poll.test/_capell/widget-polls/' . $reference . '/results')
        ->assertOk()
        ->assertJson(['results' => [$optionId => 0]]);
});
