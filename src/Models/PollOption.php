<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $poll_id
 * @property string $stable_id
 * @property string $label
 * @property int $position
 * @property int $votes_count
 * @property CarbonImmutable|null $archived_at
 */
final class PollOption extends Model
{
    protected $table = 'capell_poll_options';

    protected $guarded = [];

    /** @return BelongsTo<Poll, $this> */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    protected function casts(): array
    {
        return ['votes_count' => 'integer', 'position' => 'integer', 'archived_at' => 'immutable_datetime'];
    }
}
