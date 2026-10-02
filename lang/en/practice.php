<?php

declare(strict_types=1);

// Practising one source and exporting it for an LLM — outside the daily ritual (spec 003).

return [
    'practice' => [
        'title' => 'Practice',
        'start' => 'Practise this source',
        'banner' => 'Practice — this does not count toward your day.',
        'empty' => 'Nothing to practise here yet: this source has no active passages.',
        'complete' => [
            'title' => 'Practice complete',
            'body' => 'Your day and streak are exactly as you left them.',
        ],
        'another_set' => 'Another set',
        'back_to_source' => 'Back to source',
    ],
    'export' => [
        'title' => 'Study with an AI',
        'start' => 'Study with an AI',
        'summary' => ':passages and :questions.',
        'summary_passages' => ':count passage|:count passages',
        'summary_cards' => ':count question|:count questions',
        'privacy' => 'This text goes to whichever AI service you paste it into. byagain sends it nowhere.',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'copy_fallback' => 'Selected — copy it with your device\'s menu.',
        'download' => 'Download',
        'empty' => 'Nothing to export: this source has no active passages.',
        'instruction' => <<<'INSTRUCTION'
Act as my study partner for the passages below, which I saved from what I read. Follow these rules:

1. Quiz me one question at a time. Wait for my answer before moving to the next question.

2. Do not reveal an answer before I try to answer.

3. Judge my answer by comparing it to the passages. If I am wrong or only partly right, quote the relevant line from a passage.

4. Use the "Questions" section below if it exists — these are questions I prepared. Write other questions from the passages themselves.

5. Speak to me in whatever language the passages are written in.

6. When I say "stop", tell me which passages I struggled with the most.
INSTRUCTION,
    ],
];
