<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Data;

use Spatie\LaravelData\Data;

final class PollVoteResultData extends Data
{
    /** @param array<string, int>|null $results */
    public function __construct(public bool $accepted, public bool $hasVoted, public ?array $results) {}
}
