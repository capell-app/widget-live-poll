<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Actions;

use Capell\WidgetLivePoll\Models\Poll;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Clearing the voter cookie must not give one address unlimited votes. Accepted votes are counted per poll
 * and per address for a day in the cache under a keyed hash, so the address is never stored with a vote.
 */
final class LimitDailyVotesPerAddressAction
{
    use AsFake;
    use AsObject;

    private const int WINDOW_SECONDS = 86400;

    public function handle(Poll $poll, ?string $address): bool
    {
        return $address === null || $address === '' || $this->count($poll, $address) < $this->limit();
    }

    public function record(Poll $poll, ?string $address): void
    {
        if ($address === null || $address === '') {
            return;
        }

        $key = $this->key($poll, $address);
        Cache::add($key, 0, self::WINDOW_SECONDS);
        Cache::increment($key);
    }

    private function count(Poll $poll, string $address): int
    {
        $count = Cache::get($this->key($poll, $address), 0);

        // The Redis store returns a stored number as a numeric string.
        return is_numeric($count) ? (int) $count : 0;
    }

    private function limit(): int
    {
        $limit = Config::get('capell-widget-live-poll.daily_votes_per_address', 25);

        return is_numeric($limit) ? max(1, (int) $limit) : 25;
    }

    private function key(Poll $poll, string $address): string
    {
        $secret = Config::get('app.key');
        $pollKey = $poll->id;

        return 'capell:live-poll:address-votes:' . $pollKey . ':' . hash_hmac('sha256', $address, is_string($secret) ? $secret : '');
    }
}
