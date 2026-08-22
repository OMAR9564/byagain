<?php

declare(strict_types=1);

// Email copy. The cards are embedded in the message itself — a single call to
// action, and never a nag (FR-060, FR-062).
return [
    'daily' => [
        'subject' => 'Your review for :date',
        'greeting' => 'Here is today.',
        'cta' => 'Open in byagain',
    ],

    'reminder' => [
        'subject' => 'Your review is waiting',
        'greeting' => 'No rush — it will keep.',
        'remaining' => '{0}Nothing left, in fact.|{1}One card left.|[2,*]:count cards left.',
        'body' => 'Two minutes if you have them.',
        'cta' => 'Read today\'s review',
    ],

    'footer' => [
        'unsubscribe' => 'Turn these emails off',
        'settings' => 'Email settings',
        'source' => 'byagain is free software.',
    ],

    'unsubscribed' => [
        'title' => 'Done — you will not get these again.',
        'body' => 'You can turn them back on any time in your settings.',
    ],
];
