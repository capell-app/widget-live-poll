<?php

declare(strict_types=1);
use Capell\WidgetLivePoll\Http\Controllers\PollBootstrapController;
use Capell\WidgetLivePoll\Http\Controllers\PollResultsController;
use Capell\WidgetLivePoll\Http\Controllers\PollVoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('_capell/widget-polls/{reference}')->middleware('web')->group(function (): void {
    Route::get('bootstrap', PollBootstrapController::class)->middleware('throttle:capell-live-poll-bootstrap')->name('capell.widget-live-poll.bootstrap');
    Route::post('vote', PollVoteController::class)->middleware('throttle:capell-live-poll-vote')->name('capell.widget-live-poll.vote');
    Route::get('results', PollResultsController::class)->middleware('throttle:capell-live-poll-results')->name('capell.widget-live-poll.results');
});
