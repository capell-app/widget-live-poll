<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Actions;

use Capell\WidgetLivePoll\Data\PollPublicationData;
use Capell\WidgetLivePoll\Models\Poll;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

final class SyncPublishedPollAction
{
    use AsObject;

    public function handle(PollPublicationData $data): Poll
    {
        return DB::transaction(function () use ($data): Poll {
            $poll = Poll::query()->updateOrCreate(['site_id' => $data->siteId, 'pageable_type' => $data->pageableType, 'pageable_id' => $data->pageableId, 'language_id' => $data->languageId, 'widget_instance_id' => $data->widgetInstanceId], ['question' => $data->question, 'opens_at' => $data->opensAt, 'closes_at' => $data->closesAt, 'results_visibility' => $data->resultsVisibility, 'is_active' => true]);
            $activeIds = [];
            foreach ($data->options as $position => $option) {
                $activeIds[] = $option->id;
                $poll->options()->updateOrCreate(['stable_id' => $option->id], ['label' => $option->label, 'position' => $position, 'archived_at' => null]);
            }$removed = $poll->options()->whereNotIn('stable_id', $activeIds)->get();
            foreach ($removed as $option) {
                if ($option->votes_count > 0) {
                    $option->forceFill(['archived_at' => now()])->save();
                } else {
                    $option->delete();
                }
            }

            return $poll->fresh('options') ?? $poll;
        });
    }
}
