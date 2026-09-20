import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

test('CMS overview renders metrics, shortcuts and responsive layout', async ({ page }) => {
    const entry = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'))['resources/admin/src/main.jsx'];
    await page.route('**/admin/cms/dashboard', route => route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8">${(entry.css ?? []).map(file => `<link rel="stylesheet" href="/build/${file}">`).join('')}</head><body><div id="admin-root"></div><script type="module" src="/build/${entry.file}"></script></body></html>` }));
    await page.route('**/admin/api/**', route => {
        const path = new URL(route.request().url()).pathname;
        const json = data => route.fulfill({ json: { data } });
        if (path.endsWith('/me')) return json({ id: 1, permissions: ['cms.view', 'cms.post.view', 'cms.media.manage', 'cms.menu.manage'], module_navigation: [{ key: 'cms-dashboard', label: 'Tổng quan', module_key: 'cms', source: 'module', route: '/admin/cms/dashboard', permission: 'cms.view' }], current_website: { website_key: 'website-main' } });
        if (path.endsWith('/cms/dashboard')) return json({ pages: 8, media: 124, menus: 3, posts: { total: 40, published: 30, draft: 8, scheduled: 2 }, recent_posts: [{ id: 1, title: 'Bài viết mới nhất', status: 'draft', publish_at: null, updated_at: '2026-09-20T03:00:00Z' }] });
        return json({ items: [] });
    });
    await page.goto('/admin/cms/dashboard');
    await expect(page.getByRole('heading', { name: 'Tổng quan CMS' })).toBeVisible();
    await expect(page.locator('.cms-overview-metric')).toHaveCount(4);
    await expect(page.getByText('Bài viết mới nhất', { exact: true })).toBeVisible();
    await expect(page.locator('.cms-overview-shortcuts').getByRole('link', { name: /Tin tức/ })).toHaveAttribute('href', '/admin/cms/posts');
    await page.screenshot({ path: 'storage/framework/testing/cms-dashboard-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByRole('heading', { name: 'Tổng quan CMS' })).toBeVisible();
    expect(await page.locator('.cms-overview').evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBeTruthy();
});
