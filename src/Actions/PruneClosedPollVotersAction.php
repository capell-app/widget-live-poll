<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Actions;

use Capell\WidgetLivePoll\Models\PollVote;
use Lorisleiva\Actions\Concerns\AsObject;

final class PruneClosedPollVotersAction
{
    use AsObject;

    public function handle(): int
    {
        $deleted = PollVote::query()->whereHas('poll', static fn ($query) => $query->whereNotNull('closes_at')->where('closes_at', '<=', now()->subDays(90)))->delete();

        return is_int($deleted) ? $deleted : 0;
    }
}
