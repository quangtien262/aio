import { expect, test } from '@playwright/test';

const adminEmail = process.env.PLAYWRIGHT_ADMIN_EMAIL || 'admin@aio.local';
const adminPassword = process.env.PLAYWRIGHT_ADMIN_PASSWORD || 'password';

async function loginAsAdmin(page) {
    await page.goto('/admin/login');
    await page.locator('input[name="email"]').fill(adminEmail);
    await page.locator('input[name="password"]').fill(adminPassword);
    await page.getByRole('button', { name: /đăng nhập admin/i }).click();
    await page.waitForURL(/\/admin(\/|$)/);
}

async function selectEntityFilter(page, drawer, label) {
    await drawer.locator('.ant-select').first().click();
    await page.locator('.ant-select-dropdown:not(.ant-select-dropdown-hidden)').getByText(label, { exact: true }).click();
}

async function openThemeManager(page) {
    await page.goto('/admin/themes');
    await expect(page.getByRole('button', { name: /quản lý ngôn ngữ/i })).toBeVisible();
}

async function openLocaleManager(page) {
    await openThemeManager(page);
    await page.getByRole('button', { name: /quản lý ngôn ngữ/i }).click();

    const drawer = page.getByRole('dialog', { name: /quản lý ngôn ngữ/i });
    await expect(drawer).toBeVisible();

    return drawer;
}

test('admin can switch locale and use compact theme translation drawer', async ({ page }) => {
    await loginAsAdmin(page);

    await openThemeManager(page);
    await page.getByRole('button', { name: /frontend translations/i }).click();

    const drawer = page.getByRole('dialog', { name: /frontend translations/i });
    await expect(drawer).toBeVisible();

    await drawer.locator('.ant-segmented').nth(0).getByText('English', { exact: true }).click();
    await drawer.locator('.ant-segmented').nth(1).getByText('Business content', { exact: true }).click();

    await selectEntityFilter(page, drawer, 'CMS page');

    const searchInput = drawer.getByRole('searchbox');
    await searchInput.fill('cms_page.1.title');
    await drawer.getByRole('button', { name: 'search' }).click();
    await expect(drawer.getByText('cms_page.1.title')).toBeVisible();
    await expect(drawer.getByText(/1-1 \/ 1 items/i)).toBeVisible();

    await drawer.getByRole('button', { name: 'Edit' }).click();
    await expect(page.getByRole('dialog', { name: /cms page/i })).toBeVisible();
    await expect(page.getByRole('textbox')).not.toHaveValue('');
});

test('admin can change default locale from locale manager and switch it back', async ({ page }) => {
    await loginAsAdmin(page);

    const drawer = await openLocaleManager(page);
    const translationButton = page.getByRole('button', { name: /frontend translations/i });

    await expect(drawer.getByText(/default:\s*vi/i)).toBeVisible();
    await expect(translationButton).toContainText(/default\s+vi/i);

    const enItem = drawer.locator('.ant-list-item').filter({ hasText: 'English' });
    await enItem.getByRole('button', { name: /đặt mặc định/i }).click();

    await expect(drawer.getByText(/default:\s*en/i)).toBeVisible();
    await expect(translationButton).toContainText(/default\s+en/i);

    const viItem = drawer.locator('.ant-list-item').filter({ hasText: 'Tiếng Việt' });
    await viItem.getByRole('button', { name: /đặt mặc định/i }).click();

    await expect(drawer.getByText(/default:\s*vi/i)).toBeVisible();
    await expect(translationButton).toContainText(/default\s+vi/i);
});
