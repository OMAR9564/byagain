<?php

declare(strict_types=1);

return [
    'not_found' => [
        'title' => 'Not here',
        'body' => 'That page does not exist, or it is not yours.',
    ],

    'forbidden' => [
        'title' => 'Not allowed',
        'body' => 'You do not have access to that.',
    ],

    'server' => [
        'title' => 'Something broke on our side',
        'body' => 'Your highlights are safe. Try again in a moment.',
    ],

    'maintenance' => [
        'title' => 'Back shortly',
        'body' => 'byagain is being updated.',
    ],

    'suspended' => [
        'title' => 'Account suspended',
        'body' => 'Get in touch if you think this is a mistake.',
    ],

    'signature_invalid' => 'That link is no longer valid.',
    'offline' => 'You are offline.',
];
