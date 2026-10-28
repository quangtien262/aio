import { defineConfig } from '@playwright/test';
export default defineConfig({
    outputDir: '/private/tmp/aio-pharmacy-browser-test-results', testDir: '.', testMatch: 'pharmacy-workspace.spec.js', timeout: 45000, workers: 1,
    use: { baseURL: 'http://127.0.0.1:8128', channel: process.env.PHARMACY_UI_BROWSER_CHANNEL || undefined, headless: true, screenshot: 'only-on-failure' },
    webServer: { command: 'node tests/Support/pharmacy-ui-harness.mjs', cwd: new URL('../../', import.meta.url).pathname, url: 'http://127.0.0.1:8128', reuseExistingServer: false },
    reporter: [['list']],
});
