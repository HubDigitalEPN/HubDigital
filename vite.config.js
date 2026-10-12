import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/portal.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/storage/framework/views/**',
                '**/storage/app/signature-crl/**',
                '**/.local/**',
                '**/resources/signature-trust/**',
                '**/tools/pdf-signature/target/**',
                '**/docs/**',
            ],
        },
    },
});
