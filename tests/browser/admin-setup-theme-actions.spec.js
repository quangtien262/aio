import { expect, test } from '@playwright/test';

const adminUsername = process.env.PLAYWRIGHT_ADMIN_USERNAME || 'admin';
const adminPassword = process.env.PLAYWRIGHT_ADMIN_PASSWORD || 'password';

async function loginAsAdmin(page) {
    await page.goto('/vi');
    await page.locator('[data-open-auth-modal="login"]').first().click();
    await expect(page.locator('[data-th-modal-panel="login"]')).toBeVisible();
    await page.locator('[data-th-auth-form="login"] input[name="login"]').fill(adminUsername);
    await page.locator('[data-th-auth-form="login"] input[name="password"]').fill(adminPassword);
    await page.locator('[data-th-auth-form="login"] .th-modal-submit').click();
    await expect(page.locator('[data-th-modal-panel="login"]')).toBeHidden();
}

async function openSetupAction(page, menuName) {
    await page.goto('/admin/setup');
    const menuItem = page.getByRole('menuitem', { name: menuName });

    await expect(menuItem).toBeVisible();

    return menuItem;
}

test('setup theme actions route into working theme manager flows', async ({ page }) => {
    await loginAsAdmin(page);

    const directOpenActions = [
        { menuName: /quản lý ngôn ngữ/i, dialogName: /quản lý ngôn ngữ/i },
        { menuName: /bản dịch của theme/i, dialogName: /bản dịch của theme/i },
        { menuName: /bản dịch frontend/i, dialogName: /frontend translations/i },
        { menuName: /tạo data test/i, dialogName: /tạo data test/i },
        { menuName: /rebuild curated local demo/i, dialogName: /rebuild curated local demo/i },
    ];

    for (const action of directOpenActions) {
        const menuItem = await openSetupAction(page, action.menuName);
        await menuItem.click();

        await expect(page).toHaveURL(/\/admin\/themes/);
        await expect(page.getByRole('dialog', { name: action.dialogName })).toBeVisible();
    }

    const conditionalActions = [
        { menuName: /palette theme/i, dialogName: /palette theme/i },
        { menuName: /xóa data test/i, dialogName: /xóa data test/i },
    ];

    for (const action of conditionalActions) {
        const setupMenuItem = await openSetupAction(page, action.menuName);
        const isDisabled = (await setupMenuItem.getAttribute('aria-disabled')) === 'true';

        if (isDisabled) {
            await page.goto('/admin/themes');
            await expect(page.getByRole('menuitem', { name: action.menuName })).toHaveAttribute('aria-disabled', 'true');
            continue;
        }

        await setupMenuItem.click();
        await expect(page).toHaveURL(/\/admin\/themes/);
        await expect(page.getByRole('dialog', { name: action.dialogName })).toBeVisible();
    }
});
