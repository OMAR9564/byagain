<?php

declare(strict_types=1);

/*
 * How the top of the app says hello.
 *
 * Four parts of the day rather than one greeting, because "good morning" at
 * eleven at night is the sort of small wrongness that makes software feel
 * unattended. App\Services\Identity\Greeting picks the key from the reader's
 * own clock, not the server's.
 */
return [
    'morning' => 'Good morning',
    'afternoon' => 'Good afternoon',
    'evening' => 'Good evening',

    // Between midnight and five. Nobody awake at three wants to be told it
    // is morning.
    'night' => 'Still up',
];
