@php /** @var \Capell\WidgetLivePoll\Data\LivePollRenderData $renderData */ $liveOrigin = config('capell-widget-live-poll.live_origin'); @endphp
<section
    class="capell-live-poll"
    data-capell-live-poll
    data-message-duplicate="{{ __('capell-widget-live-poll::widget.duplicate') }}"
    data-message-error="{{ __('capell-widget-live-poll::widget.error') }}"
    data-message-counted="{{ __('capell-widget-live-poll::widget.counted') }}"
    data-message-prepare-error="{{ __('capell-widget-live-poll::widget.prepare_error') }}"
    @if ($renderData->available) data-bootstrap-url="{{ $renderData->bootstrapUrl }}" data-vote-url="{{ $renderData->voteUrl }}" data-results-url="{{ $renderData->resultsUrl }}" @endif
>
    <h2>{{ $renderData->question }}</h2>
    @if ($renderData->available && $renderData->open)
        <form data-poll-form>
            <fieldset>
                <legend class="capell-live-poll__legend">
                    {{ __('capell-widget-live-poll::widget.choose') }}
                </legend>
                @foreach ($renderData->options as $option)
                    <label
                        ><input
                            type="radio"
                            name="option_id"
                            value="{{ $option->id }}"
                            required
                        /><span>{{ $option->label }}</span></label
                    >
                @endforeach
            </fieldset>
            <button type="submit">
                {{ __('capell-widget-live-poll::widget.vote') }}
            </button>
        </form>
    @else
        <p>
            {{ $renderData->available?__('capell-widget-live-poll::widget.closed'):__('capell-widget-live-poll::widget.unavailable') }}
        </p>
    @endif
    <div
        class="capell-live-poll__results"
        data-poll-results
        @if (! $renderData->showResults) hidden @endif
    >
        <h3>{{ __('capell-widget-live-poll::widget.results') }}</h3>
        <ol>
            @foreach ($renderData->options as $option)
                <li data-option-result="{{ $option->id }}">
                    <span>{{ $option->label }}</span
                    ><strong>
                        @if ($renderData->showResults) {{ $option->votesCount }}@endif
                    </strong>
                </li>
            @endforeach
        </ol>
    </div>
    <p role="status" aria-live="polite" data-poll-status></p>
    <noscript>
        @if (is_string($liveOrigin) && $liveOrigin !== '')
            <a
                href="{{ $liveOrigin }}"
                >{{ __('capell-widget-live-poll::widget.live_site') }}</a
            >
        @else
            {{ __('capell-widget-live-poll::widget.unavailable') }}
        @endif
    </noscript>
</section>
