<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Manifest;

use Capell\Core\Contracts\Extensions\RunsScheduledExtensionJob;

final class LivePollPruneScheduleContribution implements RunsScheduledExtensionJob
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
