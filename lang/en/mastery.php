<?php

declare(strict_types=1);

// Mastery cards. The feedback wording is about how recall *felt*, never about
// being right or wrong — there is no score to lose here (SPEC 6).
return [
    'title' => 'Cards',

    'card' => [
        'question' => 'Question',
        'answer' => 'Answer',
        'show_answer' => 'Show answer',
        'cloze_hint' => 'Wrap the hidden part in {{curly braces}}.',
    ],

    // The question is "when do you want to see this again?", never "were you
    // right?" — there is no score to lose here (FR-046).
    'feedback' => [
        'sooner' => 'Show me sooner',
        'later' => 'Later is fine',
        'someday' => 'Someday',
        'learned' => 'I know this',
    ],

    'status' => [
        'active' => 'Active',
        'paused' => 'Paused',
        'retired' => 'Retired',
    ],

    'struggle_hint' => 'This one keeps slipping. Try rewriting it as a smaller question.',

    'empty' => [
        'title' => 'No cards yet',
        'body' => 'Turn a highlight into a card when you want to remember it, not just meet it again.',
    ],
];
