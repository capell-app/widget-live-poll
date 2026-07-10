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
        $results = $this->votes->results($poll);
        $etag = '"' . hash('sha256', json_encode($results, JSON_THROW_ON_ERROR)) . '"';
        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304)->header('ETag', $etag)->header('Cache-Control', 'public, max-age=30');
        }

        return response()->json(['results' => $results])->header('ETag', $etag)->header('Cache-Control', 'public, max-age=30');
    }
}
