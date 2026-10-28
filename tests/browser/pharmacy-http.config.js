import { defineConfig } from '@playwright/test';
export default defineConfig({
    outputDir: '/private/tmp/aio-pharmacy-http-results', testDir: '.', testMatch: 'pharmacy-http.spec.js', timeout: 180000, workers: 1,
    use: { baseURL: 'http://127.0.0.1:8131', channel: process.env.PHARMACY_UI_BROWSER_CHANNEL || undefined, headless: true, screenshot: 'only-on-failure', trace: 'retain-on-failure' },
    webServer: { command: 'node tests/Support/pharmacy-http-server.mjs', cwd: new URL('../../', import.meta.url).pathname, url: 'http://127.0.0.1:8131/admin/pharmacy/login', reuseExistingServer: true },
    reporter: [['list']],
});
