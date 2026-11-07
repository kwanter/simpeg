import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    // laravel-vite-plugin widens the host to 0.0.0.0 whenever LARAVEL_SAIL is
    // set, exposing the dev server and /__open-in-editor to the LAN. Keep the
    // bind loopback and opt in explicitly with `npm run dev -- --host 0.0.0.0`.
    server: {
        host: '127.0.0.1',
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});
