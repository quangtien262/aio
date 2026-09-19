import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

test('setup opens sitemap summary and refreshes it', async ({ page }) => {
    page.on('pageerror', error => console.log('PAGE ERROR:', error.stack));
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const entry = manifest['resources/admin/src/main.jsx'];
    let refreshes = 0;
    await page.route('**/admin/setup', route => route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8"><meta name="csrf-token" content="test">${(entry.css ?? []).map(file => `<link rel="stylesheet" href="/build/${file}">`).join('')}</head><body><div id="admin-root"></div><script type="module" src="/build/${entry.file}"></script></body></html>` }));
    await page.route('**/admin/api/**', route => {
        const path = new URL(route.request().url()).pathname;
        const json = data => route.fulfill({ json: { data } });
        if (path.endsWith('/me')) return json({ id: 1, permissions: ['setup.view', 'setup.complete'], module_navigation: [], current_website: { website_key: 'website-main' } });
        if (path.endsWith('/setup')) return json({ site_name: 'Sitemap test', branding: {}, steps: [], completed_steps: [], signals: {}, locale_options: [], website_types: [], progress_percent: 100 });
        if (path.endsWith('/themes')) return json([]);
        if (path.endsWith('/themes/locales')) return json({ source_locale: 'vi', locales: [{ code: 'vi', name: 'Tiếng Việt', is_enabled_for_editing: true, is_published: true }] });
        if (path.endsWith('/sitemap') || path.endsWith('/sitemap/refresh')) {
            if (path.endsWith('/refresh')) refreshes++;
            return json({ url: 'https://example.test/sitemap.xml', total: refreshes ? 12 : 10, generated_at: '2026-09-19T10:00:00+07:00', files: [{ name: 'posts-1.xml', count: refreshes ? 12 : 10, url: 'https://example.test/sitemaps/posts-1.xml' }] });
        }
        return json({ items: [] });
    });
    await page.goto('/admin/setup');
    await page.getByRole('button', { name: /Sitemap$/ }).click();
    const modal = page.getByRole('dialog', { name: 'Sitemap website' });
    await expect(modal.getByText(/10 URL/)).toBeVisible();
    await expect(modal.getByRole('link', { name: 'Mở sitemap' })).toHaveAttribute('href', 'https://example.test/sitemap.xml');
    await modal.getByRole('button', { name: /Làm mới$/ }).click();
    await expect(modal.getByText(/12 URL/)).toBeVisible();
    expect(refreshes).toBe(1);
});
