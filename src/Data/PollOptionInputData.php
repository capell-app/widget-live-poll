<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Data;

use Spatie\LaravelData\Data;

final class PollOptionInputData extends Data
{
    public function __construct(public string $id, public string $label) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['id' => ['required', 'uuid'], 'label' => ['required', 'string', 'max:200']];
    }
}
