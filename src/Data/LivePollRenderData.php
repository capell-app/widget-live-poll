<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class LivePollRenderData extends Data
{
    /** @param DataCollection<int, LivePollRenderOptionData> $options */
    public function __construct(public string $question, #[DataCollectionOf(LivePollRenderOptionData::class)] public DataCollection $options, public bool $available = false, public bool $open = false, public ?string $bootstrapUrl = null, public ?string $voteUrl = null, public ?string $resultsUrl = null, public string $resultsVisibility = 'after_vote', public bool $showResults = false) {}
}
