<?php

declare(strict_types=1);

// The one-handed editor. Text entered here has to look right on a phone.
return [
    'title' => 'New highlight',
    'title_edit' => 'Edit highlight',

    'placeholder' => 'Paste or type the passage…',
    'note_placeholder' => 'Why does this matter to you?',

    'tab' => [
        'write' => 'Write',
        'preview' => 'Preview',
    ],

    'preview' => [
        'pending' => 'Rendering…',
        'empty' => 'Nothing to preview yet.',

        // The preview is rendered on the server, so it is the one part of the
        // editor that needs a connection. Say that plainly — what was typed is
        // safe either way.
        'failed' => 'Could not render the preview. Your text is still here.',
    ],

    'format' => [
        'bold' => 'Bold',
        'italic' => 'Italic',
        'quote' => 'Quote',
        'list' => 'List',
        'code' => 'Code',
        'heading' => 'Heading',
    ],

    'draft' => [
        'saved' => 'Draft saved',
        'restored' => 'Restored your unsaved draft.',
    ],

    'cleaned' => 'Cleaned up the line breaks from your paste.',

    'saved_to' => 'Saved to :source.',
    'view_source' => 'Open source',
    'saved_with_cards' => '{1} Saved. 1 card created.|[2,*] Saved. :count cards created.',
];
