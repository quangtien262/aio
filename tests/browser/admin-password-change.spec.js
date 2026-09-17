import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

// Exercise the real compiled React app with synthetic API responses, without
// changing credentials or sessions in the developer's database.
test('forced password change unlocks dashboard and module navigation without a reload', async ({ page }) => {
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const entry = manifest['resources/admin/src/main.jsx'];
    let mustChange = true;
    let attempts = 0;
    let dashboardRequests = 0;
    let moduleRequests = 0;
    let documentRequests = 0;
    const blockedRequests = [];

    await page.route('**/admin/dashboard', async (route) => {
        documentRequests++;
        await route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head>
            <meta name="csrf-token" content="test-token">
            ${(entry.css ?? []).map((file) => `<link rel="stylesheet" href="/build/${file}">`).join('')}
            </head><body><div id="admin-root"></div>
            <script type="module" src="/build/${entry.file}"></script></body></html>` });
    });
    await page.route('**/admin/api/**', async (route) => {
        const path = new URL(route.request().url()).pathname;
        const json = (body, status = 200) => route.fulfill({ status, json: body });
        if (path === '/admin/api/me') {
            return json({ data: {
                id: 1, name: 'Test Admin', must_change_password: mustChange,
                permissions: ['platform.dashboard.view', 'store.module.view'],
                current_website: { website_key: 'website-main' },
            } });
        }
        if (path === '/admin/api/me/password') {
            expect(route.request().method()).toBe('PUT');
            attempts++;
            if (attempts === 1) return json({ message: 'Mật khẩu hiện tại không đúng.' }, 422);
            mustChange = false;
            return json({ message: 'Đã đổi mật khẩu.' });
        }
        if (mustChange) {
            blockedRequests.push(path);
            return json({ message: 'Bạn phải đổi mật khẩu trước khi tiếp tục.', code: 'password_change_required' }, 403);
        }
        if (path === '/admin/api/modules') {
            moduleRequests++;
            return json({ data: [] });
        }
        if (path === '/admin/api/dashboard') {
            dashboardRequests++;
            return json({ active_modules: [{ key: 'cms', name: 'CMS test ready', route: '/admin/cms/pages' }] });
        }
        return json({ data: [] });
    });

    await page.goto('/admin/dashboard');
    const modal = page.getByRole('dialog', { name: 'Đổi mật khẩu trước khi tiếp tục' });
    await expect(modal).toBeVisible();
    await modal.getByLabel('Mật khẩu hiện tại', { exact: true }).fill('Synthetic-Old-Password1!');
    await modal.getByLabel('Mật khẩu mới', { exact: true }).fill('Synthetic-New-Password2!');
    await modal.getByLabel('Xác nhận mật khẩu', { exact: true }).fill('Synthetic-New-Password2!');
    await modal.getByRole('button', { name: /Lưu$/ }).click();
    await expect(page.getByText('Mật khẩu hiện tại không đúng.', { exact: true })).toBeVisible();
    await expect(modal).toBeVisible();
    expect(dashboardRequests).toBe(0);
    expect(moduleRequests).toBe(0);

    await modal.getByRole('button', { name: /Lưu$/ }).click();
    await expect(modal).toBeHidden();
    await expect(page.getByText('CMS test ready', { exact: true })).toBeVisible();
    expect(dashboardRequests).toBeGreaterThan(0);
    expect(moduleRequests).toBeGreaterThan(0);
    expect(blockedRequests).toEqual([]);
    expect(documentRequests).toBe(1);
    await expect(page.getByText('Bạn phải đổi mật khẩu trước khi tiếp tục.', { exact: true })).toHaveCount(0);

    await page.getByRole('button', { name: 'Tài khoản', exact: true }).click();
    await page.getByRole('menuitem', { name: /Đổi mật khẩu$/ }).click();
    const optionalModal = page.getByRole('dialog', { name: 'Đổi mật khẩu', exact: true });
    await expect(optionalModal).toBeVisible();
    await optionalModal.getByRole('button', { name: 'Hủy', exact: true }).click();
    await expect(optionalModal).toBeHidden();
});
