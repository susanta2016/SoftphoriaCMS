import { readdirSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

// Each file directly in resources/js/tools is its own entry point, loaded
// only on the page that needs it (a tool's script on that tool's page) —
// never bundled into app.js. Shared helpers live in resources/js/tools/shared.
const toolEntries = readdirSync('resources/js/tools', { withFileTypes: true })
    .filter((entry) => entry.isFile() && entry.name.endsWith('.js'))
    .map((entry) => `resources/js/tools/${entry.name}`);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', ...toolEntries],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700, 800],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
