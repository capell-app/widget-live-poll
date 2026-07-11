<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Console\Commands;

use Capell\WidgetLivePoll\Actions\PruneClosedPollVotersAction;
use Illuminate\Console\Command;

final class PrunePollVotersCommand extends Command
{
    protected $signature = 'capell:polls:prune-voters';

    protected $description = 'Prune privacy-safe voter hashes 90 days after poll closure.';

    public function handle(): int
    {
        $count = resolve(PruneClosedPollVotersAction::class)->handle();
        $this->components->info(sprintf('Pruned %d poll voter hashes.', $count));

        return self::SUCCESS;
    }
}
