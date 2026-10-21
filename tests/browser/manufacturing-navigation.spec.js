import { expect, test } from '@playwright/test';

test.skip(process.env.PLAYWRIGHT_MANUFACTURING_FIXTURE !== '1', 'Chỉ dùng fixture Manufacturing cô lập.');

test('grouped manufacturing menus navigate on desktop and expand the active group on mobile', async ({ page }) => {
    expect(['localhost', '127.0.0.1']).toContain(new URL(test.info().project.use.baseURL).hostname);
    expect(process.env.MANUFACTURING_BROWSER_SECRET?.length).toBeGreaterThanOrEqual(32);
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.setViewportSize({ width: 1047, height: 853 });
    await page.goto(`/__manufacturing_fixture/login/${process.env.MANUFACTURING_BROWSER_SECRET}`);
    const menu = page.locator('.admin-workspace-menu');
    await expect(menu).toBeVisible();
    for (const label of ['Tổng quan', 'Chuẩn bị', 'Sản xuất', 'Thành phẩm', 'Thiết lập']) {
        await expect(menu.getByRole('menuitem', { name: new RegExp(`${label}$`) })).toBeVisible();
    }
    await menu.getByRole('menuitem', { name: /Chuẩn bị$/ }).hover();
    await page.getByRole('menuitem', { name: /Kỹ thuật sản phẩm$/ }).click();
    await expect(page).toHaveURL(/\/manufacturing\/engineering$/);
    await expect(page.getByRole('heading', { name: 'Kỹ thuật sản phẩm', exact: true })).toBeVisible();
    await expect(page.locator('.admin-breadcrumb')).toContainText('Chuẩn bị');
    await expect(menu.locator('.ant-menu-submenu-selected:visible')).toContainText('Chuẩn bị');
    await menu.getByRole('menuitem', { name: /Sản xuất$/ }).hover();
    await page.getByRole('menuitem', { name: /Sản xuất và bàn giao$/ }).click();
    await expect(page).toHaveURL(/\/manufacturing\/execution$/);
    await page.screenshot({ path: test.info().outputPath('manufacturing-menu-desktop.png'), animations: 'disabled' });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: 'Mở điều hướng admin', exact: true }).click();
    const drawer = page.getByRole('dialog', { name: 'Quản lý xưởng may', exact: true });
    await expect(drawer).toBeVisible();
    const mobile = drawer.locator('.admin-mobile-side-menu');
    await expect(mobile.getByRole('menuitem', { name: /Sản xuất và bàn giao$/ })).toHaveClass(/ant-menu-item-selected/);
    await expect(mobile.getByRole('menuitem', { name: /Chất lượng$/ })).toBeVisible();
    await mobile.getByRole('menuitem', { name: /Chất lượng$/ }).click();
    await expect(drawer).toBeHidden();
    await expect(page).toHaveURL(/\/manufacturing\/quality$/);
    expect(errors).toEqual([]);
});
