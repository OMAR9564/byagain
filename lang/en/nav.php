<?php

declare(strict_types=1);

/*
 * The bottom bar.
 *
 * Every label here is one word, and that is a layout requirement rather than a
 * style preference: five tabs across a 375px screen leave about 70px each, and
 * a two-word label wraps onto a second line, which pushes the icon up and
 * makes one tab taller than its neighbours.
 *
 * Screens keep their full titles — these are only what fits under an icon.
 */
return [
    'label' => 'Sections',

    'review' => 'Today',
    'library' => 'Library',
    'add' => 'Add',
    'mix' => 'Mix',
    'streak' => 'Streak',
];
