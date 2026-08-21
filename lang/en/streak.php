<?php

declare(strict_types=1);

// Streaks. A broken streak is stated, never scolded (FR-058).
return [
    'title' => 'Streak',

    'current' => 'Current streak',
    'longest' => 'Longest streak',
    'days' => ':count day|:count days',

    'calendar' => [
        'title' => 'Last :count days',
        'done' => 'Reviewed',
        'missed' => 'No review',
    ],

    'broken' => 'Your streak reset. Today is day one again.',
    'none' => 'Finish a review to start your streak.',
];
