<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Enums;

use Filament\Support\Contracts\HasLabel;

enum PollResultsVisibility: string implements HasLabel
{
    case Always = 'always';
    case AfterVote = 'after_vote';
    case AfterClose = 'after_close';

    public function getLabel(): string
    {
        return match ($this) {
            self::Always => __('capell-widget-live-poll::widget.results_always'),self::AfterVote => __('capell-widget-live-poll::widget.results_after_vote'),self::AfterClose => __('capell-widget-live-poll::widget.results_after_close')
        };
    }
}
