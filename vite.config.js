import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
    ],
    server: {
        host: '0.0.0.0', // Bind to all interfaces
        port: 5173,
        hmr: {
            host: 'localhost', // Use 'localhost' for host machine access
            port: 5173,
        },
    },
});
