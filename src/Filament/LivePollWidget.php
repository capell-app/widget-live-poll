<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Filament;

use Capell\Admin\Contracts\Widgets\FilamentWidget;
use Capell\WidgetLivePoll\Enums\PollResultsVisibility;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

final class LivePollWidget implements FilamentWidget
{
    public static function getWidgetName(): string
    {
        return 'capell-app.live-poll';
    }

    public static function make(): Block
    {
        return Block::make(self::getWidgetName())->label(__('capell-widget-live-poll::widget.label'))->schema([TextInput::make('question')->required()->maxLength(500), Repeater::make('options')->minItems(2)->maxItems(10)->reorderable()->schema([Hidden::make('id')->default(static fn (): string => (string) Str::uuid()), TextInput::make('label')->required()->maxLength(200)]), DateTimePicker::make('opensAt'), DateTimePicker::make('closesAt')->after('opensAt'), Select::make('resultsVisibility')->options(PollResultsVisibility::class)->default(PollResultsVisibility::AfterVote->value)->required()]);
    }
}
