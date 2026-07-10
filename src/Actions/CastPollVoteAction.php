<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Actions;

use Capell\WidgetLivePoll\Data\PollVoteResultData;
use Capell\WidgetLivePoll\Models\Poll;
use Capell\WidgetLivePoll\Models\PollOption;
use Capell\WidgetLivePoll\Models\PollVote;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

final class CastPollVoteAction
{
    use AsObject;

    public function handle(Poll $poll, string $optionId, string $voterCookie): PollVoteResultData
    {
        if (! $poll->acceptsVotes()) {
            throw new RuntimeException('Poll is not open.');
        }

        $option = $poll->options()->where('stable_id', $optionId)->whereNull('archived_at')->first();
        if (! $option instanceof PollOption) {
            throw new RuntimeException('Poll option is unavailable.');
        }

        $voterHash = $this->voterHash($poll, $voterCookie);
        $accepted = false;
        try {
            DB::transaction(function () use ($poll, $option, $voterHash, &$accepted): void {
                PollVote::query()->create(['poll_id' => $poll->getKey(), 'poll_option_id' => $option->getKey(), 'voter_hash' => $voterHash]);
                PollOption::query()->whereKey($option->getKey())->increment('votes_count');
                $accepted = true;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }
        }

        return new PollVoteResultData(accepted: $accepted, hasVoted: true, results: $this->results($poll));
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
