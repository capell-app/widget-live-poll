<?php

declare(strict_types=1);

return [
    'live_origin' => env('CAPELL_POLL_LIVE_ORIGIN'),
    // Accepted votes one internet address may cast on one poll in 24 hours, even after clearing the voter cookie.
    'daily_votes_per_address' => (int) env('CAPELL_POLL_DAILY_VOTES_PER_ADDRESS', 25),
];
