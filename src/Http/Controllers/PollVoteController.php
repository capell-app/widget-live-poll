<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Http\Controllers;

use Capell\WidgetLivePoll\Actions\CastPollVoteAction;
use Capell\WidgetLivePoll\Actions\LimitDailyVotesPerAddressAction;
use Capell\WidgetLivePoll\Models\Poll;
use Capell\WidgetLivePoll\Support\PollReferenceCodec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final readonly class PollVoteController
{
    public function __construct(private PollReferenceCodec $references) {}

    public function __invoke(Request $request, string $reference): JsonResponse
    {
        $poll = $this->references->resolve($reference);
        if (! $poll instanceof Poll) {
            abort(404);
        }
        $validated = $request->validate(['option_id' => ['required', 'uuid']]);
        $optionId = $validated['option_id'] ?? null;
        abort_unless(is_string($optionId), 422);
        $cookie = $request->cookie('capell_poll_voter');
        abort_unless(is_string($cookie) && $cookie !== '', 403);

        $limits = LimitDailyVotesPerAddressAction::make();
        if (! $limits->handle($poll, $request->ip())) {
            return response()->json(['message' => 'vote_limit_reached'], 429)->header('Cache-Control', 'private, no-store');
        }

        try {
            $result = CastPollVoteAction::run($poll, $optionId, $cookie);
        } catch (RuntimeException) {
            return response()->json(['message' => 'vote_unavailable'], 422)->header('Cache-Control', 'private, no-store');
        }

        if ($result->accepted) {
            $limits->record($poll, $request->ip());
        }

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }
}
