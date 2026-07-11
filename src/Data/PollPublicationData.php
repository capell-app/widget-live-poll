<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class PollPublicationData extends Data
{
    /** @param DataCollection<int, PollOptionInputData> $options */
    public function __construct(public int $siteId, public string $pageableType, public int $pageableId, public int $languageId, public string $widgetInstanceId, public string $question, #[DataCollectionOf(PollOptionInputData::class)] public DataCollection $options, public ?string $opensAt = null, public ?string $closesAt = null, public string $resultsVisibility = 'after_vote') {}
}
