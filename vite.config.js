import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

function resolveAdminManualChunk(id) {
    const normalizedId = id.replace(/\\/g, '/');

    const featureChunkMap = [
        { segment: '/resources/admin/src/modules/access/', chunk: 'admin-access' },
        { segment: '/resources/admin/src/modules/admins/', chunk: 'admin-admins' },
        { segment: '/resources/admin/src/modules/store/', chunk: 'admin-store' },
        { segment: '/resources/admin/src/modules/themes/', chunk: 'admin-themes' },
        { segment: '/resources/admin/src/modules/setup/', chunk: 'admin-setup' },
        { segment: '/resources/admin/src/modules/cms/', chunk: 'admin-cms' },
        { segment: '/resources/admin/src/modules/catalog/', chunk: 'admin-catalog' },
    ];

    if (normalizedId.includes('/node_modules/react/') || normalizedId.includes('/node_modules/react-dom/')) {
        return 'react-core';
    }

    if (normalizedId.includes('/node_modules/react-router/') || normalizedId.includes('/node_modules/react-router-dom/') || normalizedId.includes('/node_modules/@remix-run/router/')) {
        return 'router-core';
    }

    if (normalizedId.includes('/node_modules/antd/')) {
        return 'admin-ui-core';
    }

    const featureChunk = featureChunkMap.find(({ segment }) => normalizedId.includes(segment));

    if (featureChunk) {
        return featureChunk.chunk;
    }

    return undefined;
}

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/admin/src/main.jsx',
            ],
            refresh: true,
        }),
        react({
            babel: {
                plugins: [
                    ['import', {
                        libraryName: 'antd',
                        libraryDirectory: 'es',
                        style: false,
                    }, 'antd'],
                ],
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@admin': '/resources/admin/src',
        },
    },
    build: {
        chunkSizeWarningLimit: 950,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    return resolveAdminManualChunk(id);
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
