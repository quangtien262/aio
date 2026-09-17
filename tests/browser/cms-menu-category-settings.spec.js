import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

test('menu category settings reuse CRUD and preserve unsaved menu fields', async ({ page }) => {
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const entry = manifest['resources/admin/src/main.jsx'];
    let categories = [];
    await page.route('**/admin/cms/menus', route => route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8"><meta name="csrf-token" content="test">${(entry.css ?? []).map(file => `<link rel="stylesheet" href="/build/${file}">`).join('')}</head><body><div id="admin-root"></div><script type="module" src="/build/${entry.file}"></script></body></html>` }));
    await page.route('**/admin/api/**', route => {
        const path = new URL(route.request().url()).pathname;
        const json = data => route.fulfill({ json: { data } });
        if (path.endsWith('/me')) return json({ id: 1, permissions: ['cms.view', 'cms.menu.manage', 'cms.category.manage'], module_navigation: [{ key: 'cms-menus', label: 'Menus', module_key: 'cms', source: 'module', route: '/admin/cms/menus', permission: 'cms.view' }], current_website: { website_key: 'website-main' } });
        if (path.endsWith('/themes/locales')) return json({ source_locale: 'vi', locales: [{ code: 'vi', name: 'Tiếng Việt', is_enabled_for_editing: true, is_published: true }] });
        if (path.endsWith('/cms/menus')) return json({ items: [{ id: 1, name: 'Header', location: 'primary', items: [] }], locations: [{ label: 'Header', value: 'primary' }], linkOptions: { postCategories: [] } });
        if (path.endsWith('/cms/categories') && route.request().method() === 'POST') {
            categories = [{ ...route.request().postDataJSON(), id: 7 }];
            return json(categories[0]);
        }
        if (path.endsWith('/cms/categories')) return json({ items: categories });
        return json({ items: [] });
    });
    await page.goto('/admin/cms/menus');
    await page.getByRole('button', { name: /Thêm menu$/ }).click();
    const drawer = page.getByRole('dialog', { name: 'Tạo menu', exact: true });
    await drawer.getByLabel('Tên menu', { exact: true }).fill('Menu chưa lưu');
    await drawer.getByRole('button', { name: /Thêm mới$/ }).click();
    const item = page.getByRole('dialog', { name: 'Thêm menu', exact: true });
    await item.getByLabel('Label', { exact: true }).fill('Nhãn chưa lưu');
    await item.getByRole('button', { name: 'Cài đặt danh mục tin tức', exact: true }).click();
    const manager = page.getByRole('dialog', { name: 'Cài đặt danh mục tin tức', exact: true });
    await manager.getByRole('button', { name: /Thêm danh mục$/ }).click();
    const category = page.getByRole('dialog', { name: 'Tạo category CMS', exact: true });
    await category.getByLabel('Tên category', { exact: true }).fill('Danh mục mới');
    await expect(category.getByLabel('Slug', { exact: true })).toHaveValue('danh-muc-moi');
    await category.getByLabel('Slug', { exact: true }).fill('slug-tu-chon');
    await category.getByLabel('Tên category', { exact: true }).fill('Đổi tên danh mục');
    await expect(category.getByLabel('Slug', { exact: true })).toHaveValue('slug-tu-chon');
    await category.getByLabel('Slug', { exact: true }).fill('');
    await category.getByLabel('Tên category', { exact: true }).fill('Danh mục mới');
    await expect(category.getByLabel('Slug', { exact: true })).toHaveValue('danh-muc-moi');
    await category.getByRole('button', { name: /Lưu danh mục$/ }).click();
    await expect(category).toBeHidden();
    await expect(manager.getByText('Danh mục mới', { exact: true })).toBeVisible();
    await manager.getByRole('button', { name: 'Close', exact: true }).click();
    await expect(item.getByLabel('Label', { exact: true })).toHaveValue('Nhãn chưa lưu');
    await item.getByRole('radio', { name: 'Theo danh mục tin tức', exact: true }).check();
    await item.getByLabel('URL', { exact: true }).click();
    await page.locator('.ant-select-item-option').filter({ hasText: 'Danh mục mới' }).click();
    await expect(item.getByText('/c/danh-muc-moi', { exact: true }).last()).toBeVisible();
    await item.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(drawer.getByLabel('Tên menu', { exact: true })).toHaveValue('Menu chưa lưu');
});
