import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

test('post page size and topic filters work together', async ({ page }) => {
    const entry = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'))['resources/admin/src/main.jsx'];
    let bulkPayload;
    const items = Array.from({ length: 65 }, (_, index) => ({
        id: index + 1, title: `Bài viết ${index + 1}`, slug: `bai-viet-${index + 1}`, status: 'published',
        topic_ids: index < 2 ? [1, 2] : index < 5 ? [2] : [],
    }));
    await page.route('**/admin/cms/posts', route => route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8">${(entry.css ?? []).map(file => `<link rel="stylesheet" href="/build/${file}">`).join('')}</head><body><div id="admin-root"></div><script type="module" src="/build/${entry.file}"></script></body></html>` }));
    await page.route('**/admin/api/**', route => {
        const path = new URL(route.request().url()).pathname;
        const json = data => route.fulfill({ json: { data } });
        if (path.endsWith('/me')) return json({ id: 1, permissions: ['cms.view', 'cms.post.view', 'cms.post.update'], module_navigation: [{ key: 'cms-posts', label: 'Tin tức', module_key: 'cms', source: 'module', route: '/admin/cms/posts', permission: 'cms.post.view' }], current_website: { website_key: 'website-main' } });
        if (path.endsWith('/themes/locales')) return json({ source_locale: 'vi', locales: [{ code: 'vi', name: 'Tiếng Việt', is_enabled_for_editing: true, is_published: true }] });
        if (path.endsWith('/cms/posts/bulk')) { bulkPayload = route.request().postDataJSON(); return json({ updated: bulkPayload.ids.length }); }
        if (path.endsWith('/cms/posts')) return json({ items, categories: [], media: [], topics: [{ value: 1, label: 'Sống xanh' }, { value: 2, label: 'Công nghệ' }, { value: 3, label: 'Du lịch' }], tagOptions: [] });
        return json({ items: [] });
    });
    await page.goto('/admin/cms/posts');
    const rows = page.locator('.ant-table-tbody > tr.ant-table-row');
    const chooseSize = async size => {
        await page.locator('.ant-pagination-options .ant-select-selector').click();
        await page.locator('.ant-select-dropdown:visible .ant-select-item-option-content').filter({ hasText: new RegExp(`^${size} / page$`) }).click();
    };
    await expect(rows).toHaveCount(10);
    await chooseSize(20);
    await expect(rows).toHaveCount(20);
    await page.locator('.ant-pagination-item-2').click();
    await expect(rows.first()).toContainText('Bài viết 21');
    await expect(rows).toHaveCount(20);
    await chooseSize(50);
    await page.locator('.ant-pagination-item-1').click();
    await expect(rows).toHaveCount(50);
    await chooseSize(100);
    await expect(rows).toHaveCount(65);
    await chooseSize(10);
    await expect(rows).toHaveCount(10);
    const chooseTopic = async name => {
        await page.locator('.ant-select').filter({ has: page.getByRole('combobox', { name: 'Chuyên đề', exact: true }) }).locator('.ant-select-selector').click();
        await page.locator('.ant-select-dropdown:visible .ant-select-item-option-content').getByText(name, { exact: true }).click();
    };
    await chooseTopic('Sống xanh');
    await expect(rows).toHaveCount(2);
    await rows.first().getByRole('checkbox').check();
    await rows.last().getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Thao tác đã chọn' }).click();
    await page.getByRole('menuitem', { name: /Đổi chuyên đề/ }).click();
    const modal = page.getByRole('dialog', { name: 'Đổi chuyên đề 2 bài viết' });
    await modal.locator('.ant-select-selector').click();
    await page.locator('.ant-select-dropdown:visible .ant-select-item-option-content').getByText('Công nghệ', { exact: true }).click();
    await modal.getByRole('combobox').press('Escape');
    await modal.getByRole('button', { name: 'Lưu', exact: true }).click();
    await expect(modal).not.toBeVisible();
    expect(bulkPayload).toEqual({ ids: [1, 2], topic_ids: [2] });
    await chooseTopic('Công nghệ');
    await expect(rows).toHaveCount(5);
    await page.getByPlaceholder('Tìm theo tên, slug, danh mục, mô tả...').fill('Bài viết 3');
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText('Bài viết 3');
    await page.getByRole('button', { name: 'Xóa bộ lọc', exact: true }).click();
    await expect(rows).toHaveCount(10);
    await chooseTopic('Du lịch');
    await expect(page.getByText('Không có bài viết phù hợp với bộ lọc.')).toBeVisible();
    await page.getByRole('button', { name: 'Xóa bộ lọc', exact: true }).click();
    await expect(rows).toHaveCount(10);
});
