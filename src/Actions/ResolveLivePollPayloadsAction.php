<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Actions;

use Capell\LayoutBuilder\Contracts\WidgetExtensions\WidgetExtensionBatchPayloadResolver;
use Capell\LayoutBuilder\Data\WidgetExtensions\WidgetExtensionPayloadBatchData;
use Capell\WidgetLivePoll\Data\LivePollInputData;
use Capell\WidgetLivePoll\Data\LivePollRenderData;
use Capell\WidgetLivePoll\Data\LivePollRenderOptionData;
use Capell\WidgetLivePoll\Models\Poll;
use Capell\WidgetLivePoll\Support\PollReferenceCodec;
use Spatie\LaravelData\DataCollection;

final readonly class ResolveLivePollPayloadsAction implements WidgetExtensionBatchPayloadResolver
{
    public function __construct(private PollReferenceCodec $references, private CastPollVoteAction $votes) {}

    public function resolve(WidgetExtensionPayloadBatchData $batch): array
    {
        $instanceIds = array_map(static fn ($item): string => $item->instanceId, $batch->items);
        $polls = Poll::query()->with(['options' => static fn ($query) => $query->whereNull('archived_at')->orderBy('position')])->whereIn('widget_instance_id', $instanceIds)->where('is_active', true)->get()->keyBy('widget_instance_id');
        $resolved = [];
        foreach ($batch->items as $item) {
            $input = $item->input;
            if (! $input instanceof LivePollInputData) {
                continue;
            }

            $poll = $polls->get($item->instanceId);
            if (! $poll instanceof Poll) {
                $options = [];
                foreach ($input->options as $option) {
                    $options[] = new LivePollRenderOptionData($option->id, $option->label);
                }
                $resolved[$item->instanceId] = new LivePollRenderData($input->question, new DataCollection(LivePollRenderOptionData::class, $options));

                continue;
            }

            $reference = $this->references->encode($poll);
            $showResults = $this->votes->shouldExposeResults($poll);
            $options = [];
            foreach ($poll->options as $option) {
                $options[] = new LivePollRenderOptionData($option->stable_id, $option->label, $showResults ? $option->votes_count : 0);
            }
            $base = url('/_capell/widget-polls/' . $reference);
            $resolved[$item->instanceId] = new LivePollRenderData(question: $poll->question, options: new DataCollection(LivePollRenderOptionData::class, $options), available: true, open: $poll->acceptsVotes(), bootstrapUrl: $base . '/bootstrap', voteUrl: $base . '/vote', resultsUrl: $base . '/results', resultsVisibility: $poll->results_visibility, showResults: $showResults);
        }

        return $resolved;
    }
}
