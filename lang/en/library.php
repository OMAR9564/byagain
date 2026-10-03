<?php

declare(strict_types=1);

// Sources and the highlights that belong to them.
return [
    'title' => 'Library',

    'sources_header' => 'Sources',

    'source' => [
        'title' => 'Source',
        'title_plural' => 'Sources',
        'author' => 'Author',
        'frequency' => 'How often should this appear?',
        'highlight_count' => ':count highlight|:count highlights',
        'new' => 'New source',
        'add_passage' => 'Add passage',
        'deleted' => 'Source deleted.',
        'delete_confirm' => 'Delete this source and all its passages and cards? This cannot be undone.',
    ],

    'frequency' => [
        'never' => 'Never',
        'rare' => 'Rarely',
        'low' => 'Not often',
        'normal' => 'Normal',
        'often' => 'Often',
        'very_often' => 'Very often',
    ],

    'highlight' => [
        'title' => 'Highlight',
        'title_plural' => 'Highlights',
        'note' => 'Note',
        'location' => 'Page or location',
        'deleted' => 'Passage deleted.',
        'delete_confirm' => 'Delete this passage and its cards? This cannot be undone.',
    ],

    'empty' => [
        'title' => 'Your library is empty',
        'body' => 'Add your first source — a book, an article, anything you read.',
        'action' => 'Add your first source',
    ],
];
