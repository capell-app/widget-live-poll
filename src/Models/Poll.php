<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $site_id
 * @property int $language_id
 * @property string $widget_instance_id
 * @property string $question
 * @property string $results_visibility
 * @property bool $is_active
 * @property CarbonImmutable|null $opens_at
 * @property CarbonImmutable|null $closes_at
 */
final class Poll extends Model
{
    protected $table = 'capell_polls';

    protected $guarded = [];

    /** @return HasMany<PollOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    /** @return HasMany<PollVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function acceptsVotes(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $this->is_active && ($this->opens_at === null || $this->opens_at->lte($now)) && ($this->closes_at === null || $this->closes_at->gt($now));
    }

    protected function casts(): array
    {
        return ['opens_at' => 'immutable_datetime', 'closes_at' => 'immutable_datetime', 'is_active' => 'boolean'];
    }
}
