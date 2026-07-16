<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Actions;

use Capell\WidgetLivePoll\Data\PollVoteResultData;
use Capell\WidgetLivePoll\Enums\PollResultsVisibility;
use Capell\WidgetLivePoll\Models\Poll;
use Capell\WidgetLivePoll\Models\PollOption;
use Capell\WidgetLivePoll\Models\PollVote;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

final class CastPollVoteAction
{
    use AsFake;
    use AsObject;

    public function handle(Poll $poll, string $optionId, string $voterCookie): PollVoteResultData
    {
        $voterHash = $this->voterHash($poll, $voterCookie);
        $accepted = false;

        try {
            DB::transaction(function () use ($poll, $optionId, $voterHash, &$accepted): void {
                $lockedPoll = Poll::query()->lockForUpdate()->find($poll->getKey());
                if (! $lockedPoll instanceof Poll || ! $lockedPoll->acceptsVotes()) {
                    throw new RuntimeException('Poll is not open.');
                }

                $option = $lockedPoll->options()->where('stable_id', $optionId)->whereNull('archived_at')->lockForUpdate()->first();
                if (! $option instanceof PollOption) {
                    throw new RuntimeException('Poll option is unavailable.');
                }

                PollVote::query()->create(['poll_id' => $lockedPoll->getKey(), 'poll_option_id' => $option->getKey(), 'voter_hash' => $voterHash]);
                PollOption::query()->whereKey($option->getKey())->increment('votes_count');
                $accepted = true;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }
        }

        $poll->refresh();

        return new PollVoteResultData(accepted: $accepted, hasVoted: true, results: $this->resultsForViewer($poll, true));
    }

    public function voterHasVoted(Poll $poll, ?string $voterCookie): bool
    {
        if (! is_string($voterCookie) || $voterCookie === '') {
            return false;
        }

        $voterHash = $this->voterHash($poll, $voterCookie);

        return PollVote::query()->where('poll_id', $poll->getKey())->where('voter_hash', $voterHash)->exists();
    }

    public function shouldExposeResults(Poll $poll, bool $hasVoted = false): bool
    {
        return match (PollResultsVisibility::tryFrom($poll->results_visibility)) {
            PollResultsVisibility::Always => true,
            PollResultsVisibility::AfterVote => $hasVoted,
            PollResultsVisibility::AfterClose => $poll->hasClosed(),
            null => false,
        };
    }

    /** @return array<string, int>|null */
    public function resultsForViewer(Poll $poll, bool $hasVoted = false): ?array
    {
        if (! $this->shouldExposeResults($poll, $hasVoted)) {
            return null;
        }

        return $this->results($poll);
    }

    public function voterHash(Poll $poll, string $voterCookie): string
    {
        return hash_hmac('sha256', 'capell:live-poll:v1:' . $poll->id, $voterCookie);
    }

    /** @return array<string, int> */
    public function results(Poll $poll): array
    {
        $results = [];

        foreach ($poll->options()->get() as $option) {
            $results[$option->stable_id] = $option->votes_count;
        }

        return $results;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true) || str_contains(strtolower($exception->getMessage()), 'unique');
    }
}
