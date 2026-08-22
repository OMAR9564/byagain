<?php

declare(strict_types=1);

return [
    'title' => 'Settings',

    'review' => [
        'title' => 'Review',
        'size' => 'Cards per review',
        'size_help' => 'Between :min and :max. Changes apply to your next review.',
        'mastery_ratio' => 'Share reserved for cards',
        'quality_filter' => 'Skip very short highlights',
        'quality_filter_help' => 'Hides highlights under :count characters.',
        'equal_source_weighting' => 'Treat every source equally',
        'equal_source_weighting_help' => 'A source with more highlights will not appear more often.',
    ],

    'email' => [
        'title' => 'Email',
        'timezone' => 'Timezone',
        'daily_enabled' => 'Send my review each morning',
        'daily_time' => 'Morning send time',
        'reminder_enabled' => 'Remind me in the evening if I have not read it',
        'reminder_time' => 'Evening reminder time',
    ],

    'appearance' => [
        'title' => 'Appearance',
        'theme' => 'Theme',
        'theme_system' => 'Match my device',
        'theme_light' => 'Light',
        'theme_dark' => 'Dark',
    ],

    'account' => [
        'title' => 'Account',
        'delete' => 'Delete my account',
        'delete_help' => 'This removes your highlights permanently. It cannot be undone.',
        'delete_confirm' => 'Enter your password to confirm.',
        'deleted' => 'Your account and everything in it is gone.',
    ],

    'saved' => 'Saved.',
];
