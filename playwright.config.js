import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/browser',
    timeout: 45_000,
    expect: {
        timeout: 10_000,
    },
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000',
        headless: true,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    reporter: [['list']],
});
