<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll;

use Capell\Core\Contracts\Extensions\RegistersExtensionContentWidget;

final class WidgetLivePollContribution implements RegistersExtensionContentWidget
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
