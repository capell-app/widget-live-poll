<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class LivePollInputData extends Data
{
    /** @param DataCollection<int, PollOptionInputData> $options */
    public function __construct(public string $question, #[DataCollectionOf(PollOptionInputData::class)] public DataCollection $options, public ?string $opensAt = null, public ?string $closesAt = null, public string $resultsVisibility = 'after_vote') {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['question' => ['required', 'string', 'max:500'], 'options' => ['required', 'array', 'min:2', 'max:10'], 'opensAt' => ['nullable', 'date'], 'closesAt' => ['nullable', 'date', 'after:opensAt'], 'resultsVisibility' => ['required', 'in:always,after_vote,after_close']];
    }
}
