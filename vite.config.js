import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Two isolated entry points (SC-018). The user-facing bundle must never pull in
// Filament/Livewire/Alpine assets, so `admin.css` is built separately and is
// only ever referenced from the /admin layout.
//
// No webfonts are declared on purpose: the interface uses the system font
// stack (resources/css/tokens.css) so the first-load budget of 150KB holds
// without a single blocking font request (SC-006).
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',

                // Loaded only by the screens that need them, so the shell's
                // first paint does not pay for the review or the editor.
                'resources/js/review.js',
                'resources/js/editor.js',

                'resources/css/admin.css',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
