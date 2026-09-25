import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // One entry per area. The admin bundle carries Bootstrap and
            // AdminLTE; the frontend bundle carries Tailwind and Flux. No page
            // ever loads both.
            input: [
                'resources/css/admin/app.css',
                'resources/css/admin/app.rtl.css',
                'resources/js/admin/app.js',

                'resources/css/frontend/app.css',
                'resources/js/frontend/app.js',
                'resources/js/frontend/passkeys.js',
            ],
            refresh: true,
            fonts: [
                // Cairo is the Arabic face in both areas. The plugin asks for
                // the Latin subset only unless told otherwise, so Arabic is
                // named here rather than left to what the provider sends.
                // Only the body weight is preloaded: the rest load when a page
                // uses them, and unicode-range keeps each script's file unloaded
                // until a character of it appears.
                bunny('Cairo', {
                    weights: [400, 500, 600, 700],
                    subsets: ['arabic', 'latin'],
                    preload: [{ weight: 400 }],
                }),
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
