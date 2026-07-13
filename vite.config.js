import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: {
                app: 'resources/js/app.js',
                appCss: 'resources/css/app.css',
                upload: 'resources/js/upload-page.js',
                uploadCss: 'resources/css/upload.css',
                admin: 'resources/js/admin.js',
                adminCss: 'resources/css/admin.css',
                client: 'resources/js/client.js',
                clientCss: 'resources/css/client.css',
            },
            refresh: true,
        }),
    ],
});
