<?php

declare(strict_types=1);

// Browser notifications: the setting that turns them on, and the one
// notification the product ever sends.
return [

    // What lands on a lock screen. No passage content, no titles, no counts —
    // a reminder that today is waiting, and nothing about what is in it
    // (FR-148). Whoever is standing behind the reader learns nothing.
    'nudge' => [
        'title' => 'Today’s review is waiting',
        'body' => 'Two minutes, whenever you are ready.',
    ],

    'settings' => [
        'legend' => 'Browser notifications',
        'enabled' => 'Remind me in the browser',

        // Says what it is next to, because the reader already has an evening
        // email and the honest question is how these differ (FR-151).
        'help' => 'One reminder an hour after your morning email, and only if the review is still unfinished. Independent of the evening email.',

        // The browser said no. Asking again is something browsers punish and
        // readers resent, so the switch simply goes back to off (FR-149).
        'denied' => 'Your browser is blocking notifications for this site. You can allow them again in its site settings.',

        // Chiefly iOS: `PushManager` only exists once the app has been added
        // to the home screen, so the switch would be a promise we cannot keep
        // (R-206).
        'unsupported' => 'This browser cannot show notifications. On iPhone, add byagain to your home screen first.',

        // The server has no VAPID keys, so there is nothing to subscribe to.
        'unconfigured' => 'Notifications are not set up on this server yet.',
    ],
];
