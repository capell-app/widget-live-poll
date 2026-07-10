<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Http\Controllers;

use Capell\WidgetLivePoll\Actions\CastPollVoteAction;
use Capell\WidgetLivePoll\Models\Poll;
use Capell\WidgetLivePoll\Models\PollVote;
use Capell\WidgetLivePoll\Support\PollReferenceCodec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

final readonly class PollBootstrapController
{
    public function __construct(private PollReferenceCodec $references, private CastPollVoteAction $votes) {}

    public function __invoke(Request $request, string $reference): JsonResponse
    {
        $poll = $this->references->resolve($reference);
        if (! $poll instanceof Poll) {
            abort(404);
        }
        $cookie = $request->cookie('capell_poll_voter');
        $isNew = ! is_string($cookie) || $cookie === '';
        if ($isNew) {
            $cookie = Str::random(64);
        }$voterHash = $this->votes->voterHash($poll, $cookie);
        $hasVoted = PollVote::query()->where('poll_id', $poll->getKey())->where('voter_hash', $voterHash)->exists();
        $showResults = $poll->results_visibility === 'always' || $hasVoted || ($poll->results_visibility === 'after_close' && ! $poll->acceptsVotes());
        $response = response()->json(['csrf_token' => csrf_token(), 'has_voted' => $hasVoted, 'open' => $poll->acceptsVotes(), 'results' => $showResults ? $this->votes->results($poll) : null])->header('Cache-Control', 'private, no-store');
        if ($isNew) {
            $response->withCookie(new Cookie('capell_poll_voter', $cookie, time() + 31536000, '/', null, app()->environment('production'), true, false, Cookie::SAMESITE_LAX));
        }

        return $response;
    }
}
