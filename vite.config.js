import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        fs: {
            deny: [
                // Preserve Vite's default protections when extending its deny list.
                '.env',
                '.env.*',
                '*.{crt,pem,key,p12,pfx,cer,der}',
                '.npmrc',
                '.yarnrc.yml',
                '**/.git/**',
                '**/.local/**',
                '**/.recovery/**',
                '**/.checkpoints/**',
                '**/.agents/**',
                '**/.codex/**',
                '**/*.{sql,sqlite,sqlite3,db,dump,bak}',
                '**/*.sql.{gz,zip,bz2,xz}',
            ],
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.js', 'resources/js/logistics.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
