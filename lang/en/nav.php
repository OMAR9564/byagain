<?php

declare(strict_types=1);

/*
 * The bottom bar (four tabs) and the "Add" action in the navigation bar.
 *
 * Every label here is one word, and that is a layout requirement rather than a
 * style preference: four tabs across a 375px screen leave about 94px each.
 * A two-word label would still wrap onto a second line.
 *
 * Screens keep their full titles — these are only what fits under an icon
 * or as an aria-label on the + button in the navbar.
 */
return [
    'label' => 'Sections',

    'review' => 'Today',
    'library' => 'Library',
    'add' => 'Add',
    'mix' => 'Mix',
    'streak' => 'Streak',
];
