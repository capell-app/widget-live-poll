<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id */
final class PollVote extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'capell_poll_votes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'poll_id',
        'poll_option_id',
        'voter_hash',
        'created_at',
    ];

    /** @return BelongsTo<Poll, $this> */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /** @return BelongsTo<PollOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }
}
