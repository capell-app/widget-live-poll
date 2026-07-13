<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Http\Controllers;

use Capell\WidgetLivePoll\Actions\CastPollVoteAction;
use Capell\WidgetLivePoll\Models\Poll;
use Capell\WidgetLivePoll\Support\PollReferenceCodec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class PollResultsController
{
    public function __construct(private PollReferenceCodec $references, private CastPollVoteAction $votes) {}

    public function __invoke(Request $request, string $reference): JsonResponse
    {
        $poll = $this->references->resolve($reference);
        if (! $poll instanceof Poll) {
            abort(404);
        }
        $cookie = $request->cookie('capell_poll_voter');
        $hasVoted = $this->votes->voterHasVoted($poll, is_string($cookie) ? $cookie : null);
        $results = $this->votes->resultsForViewer($poll, $hasVoted);

        return response()->json(['results' => $results])->header('Cache-Control', 'private, no-store');
    }
}
