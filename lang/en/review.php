<?php

declare(strict_types=1);

// The review screen: the two-minute ritual the whole product exists for.
return [
    'title' => "Today's review",
    'progress' => 'Card :current of :total',

    'action' => [
        'keep' => 'Keep',
        'discard' => 'Discard',
        'favorite' => 'Favourite',
        'make_card' => 'Make a card',
        'source_frequency' => 'Show this source…',
    ],

    'complete' => [
        'title' => 'That is today.',
        'body' => 'Come back tomorrow for a new selection.',
        'streak' => 'Day :count.',
    ],

    'empty' => [
        'title' => 'Nothing to review yet',
        'body' => 'Add a few highlights and your first review will be waiting tomorrow.',
    ],

    'offline' => 'You are offline. Your progress is saved and will sync when you are back.',
];
