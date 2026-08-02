import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
   
    plugins: [
        laravel({
            // app.jsx : boutique React/Inertia.
            // dashboard.css : back-office Livewire, sans React ni Inertia.
            input: ['resources/js/app.jsx', 'resources/css/dashboard.css'],
            refresh: true,
        }),
        react(),
    ],
    
});
