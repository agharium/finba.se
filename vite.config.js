import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/transactions.css',
                'resources/css/filament/loans.css',
                'resources/css/filament/dashboard.css',
                'resources/css/filament/profile.css',
                'resources/css/filament/onboarding.css',
                'resources/css/filament/alpha-banner.css',
                'resources/css/filament/changelog.css',
                'resources/css/filament/roadmap.css',
                'resources/css/filament/pwa.css',
                'resources/js/pwa-manager.js',
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
