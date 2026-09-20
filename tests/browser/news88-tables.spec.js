import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

test('NEWS88 content tables stay readable and scroll within the article on mobile', async ({ page }) => {
    const styles = readFileSync('themes/NEWS88/views/partials/styles.blade.php', 'utf8');
    const scripts = readFileSync('themes/NEWS88/views/partials/scripts.blade.php', 'utf8');
    await page.setContent(`<html lang="vi"><head>${styles}</head><body><main class="n88-article"><article><h1>Bảng nội dung</h1><div class="n88-article-body"><table><thead><tr><th>Tình huống</th><th>Cách feature flag hỗ trợ</th><th>Trạng thái</th></tr></thead><tbody><tr><td>Canary release</td><td>Mở cho nhân viên hoặc một nhóm nhỏ trước khi rollout rộng.</td><td>Đang áp dụng</td></tr><tr><td>A/B testing</td><td>Trả về biến thể có giá trị thay vì chỉ true/false.</td><td>Đang thử nghiệm</td></tr></tbody></table></div></article></main>${scripts}</body></html>`);
    await expect(page.locator('.n88-table-scroll table')).toHaveCount(1);
    await expect(page.locator('th').first()).toHaveCSS('padding-left', '18px');
    await page.screenshot({ path: 'storage/framework/testing/news88-table-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    const wrapper = page.locator('.n88-table-scroll');
    expect(await wrapper.evaluate(el => el.scrollWidth > el.clientWidth)).toBeTruthy();
    expect(await wrapper.evaluate(el => el.getBoundingClientRect().right <= innerWidth)).toBeTruthy();
    await wrapper.focus();
    await page.keyboard.press('End');
    await page.screenshot({ path: 'storage/framework/testing/news88-table-mobile.png', fullPage: true });
});
