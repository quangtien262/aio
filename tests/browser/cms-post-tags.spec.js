import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

test('post form selects existing tags and submits new tags', async ({ page }) => {
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const entry = manifest['resources/admin/src/main.jsx'];
    const menu = { key: 'cms-posts', label: 'Tin tức', module_key: 'cms', source: 'module', route: '/admin/cms/posts', permission: 'cms.post.view' };
    let submitted;
    const highlightUpdates = [];
    let rejectDuplicateSlug = true;
    let translatedTag;
    await page.route('**/admin/cms/posts', route => route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8"><meta name="csrf-token" content="test">${(entry.css ?? []).map(file => `<link rel="stylesheet" href="/build/${file}">`).join('')}</head><body><div id="admin-root"></div><script type="module" src="/build/${entry.file}"></script></body></html>` }));
    await page.route('**/admin/api/**', route => {
        const path = new URL(route.request().url()).pathname;
        const json = data => route.fulfill({ json: { data } });
        if (path.endsWith('/cms/posts/bulk')) {
            highlightUpdates.push(route.request().postDataJSON());
            return json({});
        }
        if (path.endsWith('/me')) return json({ id: 1, permissions: ['cms.view', 'cms.post.view', 'cms.post.create', 'cms.post.update', 'cms.publish'], module_navigation: [menu], current_website: { website_key: 'website-main' } });
        if (path.endsWith('/themes/locales')) return json({ source_locale: 'vi', locales: [{ code: 'vi', name: 'Tiếng Việt', is_enabled_for_editing: true, is_published: true }, { code: 'en', name: 'English', is_enabled_for_editing: true, is_published: true }] });
        if (path.endsWith('/cms/posts') && route.request().method() === 'POST') {
            if (rejectDuplicateSlug) {
                rejectDuplicateSlug = false;
                return route.fulfill({ status: 422, json: { message: 'Validation failed', errors: { slug: ['Trường slug đã tồn tại.'] } } });
            }
            submitted = route.request().postDataJSON();
            return route.fulfill({ status: 201, json: { data: { ...submitted, id: 123 } } });
        }
        if (path.endsWith('/cms/posts/123') && route.request().method() === 'PUT') {
            submitted = route.request().postDataJSON();
            return json({ ...submitted, id: 123 });
        }
        if (path.endsWith('/cms/posts')) return json({ items: submitted ? [{ ...submitted, id: 123 }] : [], categories: [], media: [], tagOptions: [{ id: 1, label: 'Du lịch', value: 'Du lịch' }, { id: 2, label: 'Ẩm thực', value: 'Ẩm thực' }] });
        if (path.endsWith('/localization/content/cms_post/123')) return json({ fields: ['title', 'slug', 'body'], source_locale: 'vi', translations: { vi: { payload: submitted, translation_status: 'published' } } });
        if (path.endsWith('/localization/content/cms_tag/2')) return json({ translations: {} });
        if (path.endsWith('/localization/content/cms_tag/2/en')) {
            translatedTag = route.request().postDataJSON();
            return json({});
        }
        if (path.endsWith('/localization/slugs')) return json({ slug: 'bai-co-tags' });
        return json({ items: [] });
    });
    await page.goto('/admin/cms/posts');
    await page.getByRole('button', { name: /Tạo Tin tức/ }).click();
    const form = page.getByRole('dialog');
    await form.getByLabel('Tiêu đề', { exact: true }).fill('Bài có tags');
    await form.getByLabel('Mô tả ngắn', { exact: true }).fill('Mô tả tự động cho SEO');
    await expect(form.getByLabel('SEO Title', { exact: true })).toBeHidden();
    await form.getByRole('button', { name: /SEO cơ bản/ }).click();
    await expect(form.getByLabel('SEO Title', { exact: true })).toHaveValue('Bài có tags');
    await expect(form.getByLabel('SEO Description', { exact: true })).toHaveValue('Mô tả tự động cho SEO');
    await form.getByRole('button', { name: /SEO cơ bản/ }).click();
    const tags = form.getByLabel('Tags bài viết', { exact: true });
    await tags.fill('Du lịch');
    await page.locator('.ant-select-item-option').filter({ hasText: 'Du lịch' }).click();
    await tags.fill('Ẩm thực');
    await tags.press('Enter');
    await form.getByRole('button', { name: /Lưu bài viết$/ }).click();
    await expect(form.getByText('Chưa lưu được bài viết', { exact: true })).toBeVisible();
    await expect(form.locator('.ant-form-item-explain-error').filter({ hasText: 'Trường slug đã tồn tại.' })).toBeVisible();
    await expect(form.getByLabel('Tiêu đề', { exact: true })).toHaveValue('Bài có tags');
    expect(submitted).toBeUndefined();
    await form.getByLabel('Slug', { exact: true }).fill('bai-co-tags-moi');
    await form.getByRole('button', { name: /Lưu bài viết$/ }).click();
    await expect.poll(() => submitted?.tags).toEqual(['Du lịch', 'Ẩm thực']);
    expect(submitted.meta_title).toBe('Bài có tags');
    expect(submitted.meta_description).toBe('Mô tả tự động cho SEO');
    await expect(form).toBeHidden();
    const search = page.getByPlaceholder('Tìm theo tên, slug, danh mục, mô tả...');
    await search.fill('khong-co-bai-phu-hop');
    await expect(page.getByText('Không có bài viết phù hợp với bộ lọc.', { exact: true })).toBeVisible();
    await search.fill('Bài có tags');
    await expect(page.getByRole('button', { name: 'Bài có tags', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Xóa bộ lọc', exact: true }).click();
    await expect(search).toHaveValue('');
    for (const highlighted of [true, false]) {
        await page.locator('tbody tr').filter({ hasText: 'Bài có tags' }).getByRole('checkbox').check();
        await page.getByRole('button', { name: /Thao tác đã chọn/ }).click();
        await expect(page.getByRole('menuitem', { name: /Đánh dấu nổi bật|Bỏ nổi bật/ })).toHaveCount(0);
        await page.getByRole('menuitem', { name: /Nổi bật/ }).click();
        const highlightModal = page.getByRole('dialog', { name: 'Nổi bật', exact: true });
        await highlightModal.getByRole('radio', { name: highlighted ? 'Đánh dấu nổi bật' : 'Bỏ nổi bật', exact: true }).check();
        await highlightModal.getByRole('button', { name: /Lưu$/ }).click();
        await expect(highlightModal).toBeHidden();
        expect(highlightUpdates.at(-1)).toEqual({ ids: [123], is_highlight: highlighted });
    }
    await page.locator('tbody tr').filter({ hasText: 'Bài có tags' }).getByRole('checkbox').check();
    await page.getByRole('button', { name: /Thao tác đã chọn/ }).click();
    await page.getByRole('menuitem', { name: /Thời gian xuất bản/ }).click();
    const publishModal = page.getByRole('dialog', { name: 'Thời gian xuất bản', exact: true });
    const publishInput = publishModal.getByRole('textbox', { name: 'Thời gian xuất bản' });
    await publishInput.fill('18/09/2026 14:30:00');
    await publishInput.press('Enter');
    await publishModal.getByRole('button', { name: /Lưu$/ }).click();
    await expect(publishModal).toBeHidden();
    expect(highlightUpdates.at(-1)).toEqual({ ids: [123], publish_at: '2026-09-18 14:30:00' });
    await page.getByRole('button', { name: 'Bài có tags', exact: true }).click();
    await page.getByRole('button', { name: /Sửa bài viết/ }).click();
    await expect(form.locator('.ant-select-selection-item').filter({ hasText: 'Du lịch' })).toBeVisible();
    await form.locator('.ant-select-selection-item').filter({ hasText: 'Du lịch' }).locator('.ant-select-selection-item-remove').click();
    await form.getByRole('button', { name: /Lưu bài viết$/ }).click();
    await expect.poll(() => submitted?.tags).toEqual(['Ẩm thực']);
    await expect(form).toBeHidden();
    await page.getByRole('button', { name: 'Bài có tags', exact: true }).click();
    await page.getByRole('button', { name: /Sửa bài viết/ }).click();
    await form.getByRole('tab', { name: /English/ }).click();
    const confirm = page.getByRole('button', { name: 'Chuyển ngôn ngữ', exact: true });
    if (await confirm.isVisible()) await confirm.click();
    await form.getByRole('button', { name: /Dịch tên tags/ }).click();
    const tagModal = page.getByRole('dialog', { name: 'Dịch tags — EN' });
    await tagModal.getByLabel('Tên tag', { exact: true }).fill('Cuisine');
    await tagModal.getByLabel('Slug', { exact: true }).fill('cuisine');
    await tagModal.getByRole('button', { name: /Xuất bản$/ }).click();
    await expect.poll(() => translatedTag).toEqual({ payload: { name: 'Cuisine', slug: 'cuisine' }, publish: true });
    await expect(tagModal.getByText('Đã xuất bản bản dịch tag.', { exact: true })).toBeVisible();
});

