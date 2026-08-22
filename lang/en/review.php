<?php

declare(strict_types=1);

// The review screen: the two-minute ritual the whole product exists for.
return [
    'title' => "Today's review",
    'resume' => 'Pick up where you left off',
    'progress' => 'Card :current of :total',

    'action' => [
        'keep' => 'Keep',
        'discard' => 'Discard',
        'favorite' => 'Favourite',
        'make_card' => 'Make a card',
        'source_frequency' => 'Show this source…',

        // What each choice actually does, said once under the buttons. The
        // words alone are ambiguous: "discard" sounds like deletion, and it
        // is not — nothing here is ever deleted (FR-011).
        'keep_help' => 'Keeps it in rotation',
        'discard_help' => 'Stops it coming back',
    ],

    // Moving between cards. A decision is recorded, so going back is a look
    // rather than an edit — except in the moment right after it was made.
    'nav' => [
        'previous' => 'Previous card',
        'resume' => 'Back to where I was',
        'kept' => 'Kept',
        'discarded' => 'Discarded',
        'answered' => 'Answered',
    ],

    'undo' => [
        'kept' => 'Kept',
        'discarded' => 'Discarded',
        'action' => 'Undo',
    ],

    'complete' => [
        'title' => 'That is today.',
        'body' => 'Come back tomorrow for a new selection.',
        'streak' => 'Day :count.',
    ],

    // The screen you get when the day is already finished. The point of the
    // product is that finishing is possible, so this screen does not quietly
    // deal another hand — it says come back tomorrow, and puts one more round
    // behind a deliberate tap for whoever wants it anyway.
    'done' => [
        'title' => 'That is today.',
        'body' => 'Come back tomorrow for a new selection.',
        'rounds' => 'You have finished :count review today.|You have finished :count reviews today.',
        'again' => 'One more round',
        'again_help' => 'Fresh passages, and it will not change your streak.',
    ],

    'again' => [
        // Every eligible passage has been seen recently. Saying so is better
        // than dealing the same cards again under a new heading.
        'exhausted' => 'Nothing new to draw on right now. Tomorrow there will be.',
    ],

    'empty' => [
        'title' => 'Nothing to review yet',
        'body' => 'Add a few highlights and your first review will be waiting tomorrow.',
    ],

    'offline' => 'You are offline. Your progress is saved and will sync when you are back.',
];
