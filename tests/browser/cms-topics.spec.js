import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

test('manage topics and select multiple topics in a post', async ({ page }) => {
    const entry = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'))['resources/admin/src/main.jsx'];
    let topics = [{ id: 1, name: 'Sống xanh', slug: 'song-xanh', is_active: true, posts_count: 0 }];
    let savedPost;
    await page.route('**/admin/cms/posts', route => route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8"><meta name="csrf-token" content="test">${(entry.css ?? []).map(file => `<link rel="stylesheet" href="/build/${file}">`).join('')}</head><body><div id="admin-root"></div><script type="module" src="/build/${entry.file}"></script></body></html>` }));
    await page.route('**/admin/api/**', async route => {
        const path = new URL(route.request().url()).pathname;
        const method = route.request().method();
        const json = data => route.fulfill({ json: { data } });
        if (path.endsWith('/me')) return json({ id: 1, permissions: ['cms.view', 'cms.category.manage', 'cms.post.view', 'cms.post.create', 'cms.post.update', 'cms.publish'], module_navigation: [{ key: 'cms-posts', label: 'Tin tức', module_key: 'cms', source: 'module', route: '/admin/cms/posts', permission: 'cms.post.view' }], current_website: { website_key: 'website-main' } });
        if (path.endsWith('/themes/locales')) return json({ source_locale: 'vi', locales: [{ code: 'vi', name: 'Tiếng Việt', is_enabled_for_editing: true, is_published: true }] });
        if (path.endsWith('/cms/topics') && method === 'POST') { const item = { ...route.request().postDataJSON(), id: 2, posts_count: 0 }; topics.push(item); return json(item); }
        if (path.endsWith('/cms/topics')) return json({ items: topics });
        if (path.endsWith('/cms/topics/2') && method === 'PUT') { topics[1] = { ...topics[1], ...route.request().postDataJSON() }; return json(topics[1]); }
        if (path.endsWith('/cms/topics/2') && method === 'DELETE') { topics = topics.filter(item => item.id !== 2); return json({}); }
        if (path.endsWith('/cms/posts') && method === 'POST') { savedPost = route.request().postDataJSON(); return json({ id: 1, ...savedPost }); }
        if (path.endsWith('/cms/posts')) return json({ items: [], categories: [], media: [], topics: topics.map(item => ({ value: item.id, label: item.name })), tagOptions: [] });
        return json({ items: [] });
    });
    await page.goto('/admin/cms/posts');
    await page.getByRole('button', { name: 'Cài đặt tin tức', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Cài đặt danh mục tin tức', exact: true }).click();
    const categories = page.getByRole('dialog', { name: 'Cài đặt danh mục tin tức', exact: true });
    await expect(categories).toBeVisible();
    await categories.getByRole('button', { name: 'Close', exact: true }).click();
    await page.getByRole('button', { name: 'Cài đặt tin tức', exact: true }).click();
    await page.getByRole('menuitem', { name: 'QL chuyên đề', exact: true }).click();
    const manager = page.getByRole('dialog', { name: 'Quản lý chuyên đề tin tức', exact: true });
    await manager.getByRole('button', { name: 'Thêm chuyên đề', exact: true }).click();
    const form = page.getByRole('dialog', { name: 'Thêm chuyên đề', exact: true });
    await form.getByLabel('Tên chuyên đề', { exact: true }).fill('Tết mới');
    await expect(form.getByLabel('Slug', { exact: true })).toHaveValue('tet-moi');
    await form.getByRole('button', { name: 'Lưu chuyên đề' }).click();
    await expect(form).not.toBeVisible();
    await expect(manager.getByText('Tết mới', { exact: true })).toBeVisible();
    await manager.getByRole('button', { name: 'Close', exact: true }).click();
    await page.getByRole('button', { name: /Tạo Tin tức/ }).click();
    const post = page.getByRole('dialog', { name: 'Tạo bài viết CMS', exact: true });
    await post.getByLabel('Tiêu đề', { exact: true }).fill('Bài theo chuyên đề');
    await post.getByLabel('Chuyên đề', { exact: true }).click();
    await page.locator('.ant-select-item-option-content').getByText('Sống xanh', { exact: true }).click();
    await page.locator('.ant-select-item-option-content').getByText('Tết mới', { exact: true }).click();
    await post.getByLabel('Chuyên đề', { exact: true }).press('Escape');
    await post.getByRole('button', { name: 'Lưu bài viết', exact: true }).click();
    await expect(post).not.toBeVisible();
    expect(savedPost.topic_ids).toEqual([1, 2]);
});

test('menu editor offers news topic links', async ({ page }) => {
    const entry = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'))['resources/admin/src/main.jsx'];
    let saved;
    await page.route('**/admin/cms/menus', route => route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8"><meta name="csrf-token" content="test">${(entry.css ?? []).map(file => `<link rel="stylesheet" href="/build/${file}">`).join('')}</head><body><div id="admin-root"></div><script type="module" src="/build/${entry.file}"></script></body></html>` }));
    await page.route('**/admin/api/**', route => {
        const path = new URL(route.request().url()).pathname;
        const json = data => route.fulfill({ json: { data } });
        if (path.endsWith('/me')) return json({ id: 1, permissions: ['cms.view', 'cms.menu.manage'], module_navigation: [{ key: 'cms-menus', label: 'Menus', module_key: 'cms', source: 'module', route: '/admin/cms/menus', permission: 'cms.view' }], current_website: { website_key: 'website-main' } });
        if (path.endsWith('/themes/locales')) return json({ source_locale: 'vi', locales: [{ code: 'vi', name: 'Tiếng Việt', is_enabled_for_editing: true, is_published: true }] });
        if (path.endsWith('/cms/menus') && route.request().method() === 'POST') { saved = route.request().postDataJSON(); return json({ id: 1, ...saved }); }
        if (path.endsWith('/cms/menus')) return json({ items: [], locations: [{ label: 'Menu chính', value: 'primary' }], linkOptions: { postTopics: [{ label: 'Sống xanh', value: '1', url: '/topics/song-xanh' }] } });
        return json({ items: [] });
    });
    await page.goto('/admin/cms/menus');
    await page.getByRole('button', { name: /Thêm menu$/ }).click();
    const drawer = page.getByRole('dialog', { name: 'Tạo menu', exact: true });
    await drawer.getByLabel('Tên menu', { exact: true }).fill('Menu chuyên đề');
    await drawer.getByRole('button', { name: /Thêm mới$/ }).click();
    const item = page.getByRole('dialog', { name: 'Thêm menu', exact: true });
    await item.getByRole('radio', { name: 'Chuyên đề tin tức', exact: true }).check();
    await item.getByLabel('URL', { exact: true }).click();
    await page.locator('.ant-select-item-option-content').filter({ hasText: 'Sống xanh' }).click();
    await item.getByLabel('Label', { exact: true }).fill('Sống xanh');
    await item.getByRole('button', { name: 'OK', exact: true }).click();
    await drawer.getByRole('button', { name: 'Lưu menu', exact: true }).click();
    await expect(drawer).not.toBeVisible();
    expect(saved.items[0]).toMatchObject({ link_type: 'post-topic', resource_type: 'cms_topic', url: '/topics/song-xanh' });
});
