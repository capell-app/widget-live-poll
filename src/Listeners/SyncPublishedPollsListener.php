<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Listeners;

use Capell\Core\Events\PageDeleted;
use Capell\Core\Events\PageSaved;
use Capell\WidgetLivePoll\Actions\SyncPublishedPollAction;
use Capell\WidgetLivePoll\Data\LivePollInputData;
use Capell\WidgetLivePoll\Data\PollPublicationData;
use Capell\WidgetLivePoll\Models\Poll;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class SyncPublishedPollsListener
{
    public function handleSaved(PageSaved $event): void
    {
        try {
            $page = $event->page;
            if (! $page instanceof Model) {
                return;
            }

            $siteId = $page->getAttribute('site_id');
            $pageId = $page->getKey();
            $languageId = $event->formData['language_id'] ?? data_get($event->formData, 'translation.language_id');
            if (! is_int($siteId) || ! is_int($pageId) || ! is_int($languageId)) {
                return;
            }

            $seen = [];
            foreach ($this->widgets($event->formData) as $widget) {
                $instanceId = data_get($widget, 'data.__capell.instance_id') ?? data_get($widget, '__capell.instance_id');
                $state = $widget['data'] ?? $widget;
                if (! is_string($instanceId) || ! is_array($state)) {
                    continue;
                }

                $input = LivePollInputData::validateAndCreate($state);
                SyncPublishedPollAction::run(new PollPublicationData(siteId: $siteId, pageableType: $page->getMorphClass(), pageableId: $pageId, languageId: $languageId, widgetInstanceId: $instanceId, question: $input->question, options: $input->options, opensAt: $input->opensAt, closesAt: $input->closesAt, resultsVisibility: $input->resultsVisibility));
                $seen[] = $instanceId;
            }

            Poll::query()->where('site_id', $siteId)->where('pageable_type', $page->getMorphClass())->where('pageable_id', $pageId)->where('language_id', $languageId)->when($seen !== [], static fn ($query) => $query->whereNotIn('widget_instance_id', $seen))->update(['is_active' => false]);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    public function handleDeleted(PageDeleted $event): void
    {
        $page = $event->page;
        if (! $page instanceof Model) {
            return;
        }

        Poll::query()->where('pageable_type', $page->getMorphClass())->where('pageable_id', $page->getKey())->update(['is_active' => false]);
    }

    /** @return list<array<string, mixed>> */
    private function widgets(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $widgets = [];
        $key = $value['type'] ?? $value['widget_key'] ?? $value['key'] ?? null;
        if ($key === 'capell-app.live-poll') {
            $widgets[] = $value;
        }

        foreach ($value as $child) {
            $widgets = array_merge($widgets, $this->widgets($child));
        }

        return $widgets;
    }
}
