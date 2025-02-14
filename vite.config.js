import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    build: {
        outDir: 'public/build',
        manifest: true,
        base: '/',
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/react/main.tsx'],
            refresh: true,
        }),
        react(),
    ],
});
