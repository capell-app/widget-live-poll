<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Data;

use Spatie\LaravelData\Data;

final class LivePollRenderOptionData extends Data
{
    public function __construct(public string $id, public string $label, public int $votesCount = 0) {}
}
