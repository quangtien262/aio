import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

test('NEWS88 opens every empty homepage block and submits the selected block', async ({ page }) => {
    const html = execFileSync(process.env.PHP_BINARY || 'php', ['tests/Support/render-news88-editor.php'], {
        encoding: 'utf8', maxBuffer: 8 * 1024 * 1024,
    });
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.route('**/*', async (route) => {
        const url = new URL(route.request().url());
        if (url.pathname === '/vi') return route.fulfill({ contentType: 'text/html', body: html });
        if (url.pathname.endsWith('/source-preview')) return route.fulfill({ json: { data: [] } });
        if (url.pathname.startsWith('/admin/api/landing/blocks/')) {
            // Capture a save without modifying a real block or reloading the fixture.
            return route.fulfill({ status: 422, json: { message: 'Synthetic save captured' } });
        }
        return route.abort();
    });
    await page.goto('/vi?mod=admin');
    const buttons = page.locator('[data-xd-edit-block]');
    await expect(buttons).toHaveCount(8);
    const editor = page.locator('[data-xd-editor]');
    for (let index = 0; index < 8; index++) {
        const button = buttons.nth(index);
        const id = await button.getAttribute('data-xd-edit-block');
        await button.click();
        await expect(editor).toBeVisible();
        await expect(editor.locator('[data-xd-field="block_id"]')).toHaveValue(id);
        await expect(editor.locator('[data-xd-field="title"]')).not.toHaveValue('');
        await editor.locator('[data-xd-editor-close]').last().click();
        await expect(editor).toBeHidden();
    }
    const videoButton = page.locator('.n88-video-panel [data-xd-edit-block]');
    const id = await videoButton.getAttribute('data-xd-edit-block');
    expect(id).not.toBe(await page.locator('.n88-latest-panel [data-xd-edit-block]').getAttribute('data-xd-edit-block'));
    await videoButton.click();
    await editor.locator('[data-xd-field="title"]').fill('Tin nổi bật kiểm thử');
    const saved = page.waitForRequest((request) => request.method() === 'PUT' && new URL(request.url()).pathname.endsWith(`/blocks/${id}`));
    await editor.locator('button[type="submit"]').click();
    const request = await saved;
    expect(request.postDataJSON().data.title).toBe('Tin nổi bật kiểm thử');
    expect(errors).toEqual([]);
});
