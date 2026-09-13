import { expect, test } from '@playwright/test';

const adminUsername = process.env.PLAYWRIGHT_ADMIN_USERNAME || 'fnb-browser';
const adminPassword = process.env.PLAYWRIGHT_ADMIN_PASSWORD || 'FnbBrowser123!';

test.skip(process.env.PLAYWRIGHT_FNB_FIXTURE !== '1', 'Chỉ chạy trên fixture F&B cô lập được cho phép ghi dữ liệu.');

async function loginAsAdmin(page) {
    await page.goto('/vi/login');
    await page.locator('input[name="login"]').fill(adminUsername);
    await page.locator('input[name="password"]').fill(adminPassword);
    await Promise.all([
        page.waitForURL(/\/admin(?:\/|$)/),
        page.getByRole('button', { name: /đăng nhập vào hệ thống/i }).click(),
    ]);
}

async function openFnbSection(page, section) {
    await page.getByTestId(`fnb-nav-${section}`).click();
    await expect(page).toHaveURL(new RegExp(`/admin/modules/fnb-pos/${section}(?:$|[?#])`));
}

async function completeReauthentication(page) {
    const dialog = page.getByRole('dialog', { name: 'Xác nhận thao tác nhạy cảm' });
    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Mật khẩu hiện tại').fill(adminPassword);
    await dialog.getByRole('button', { name: 'Xác nhận và tiếp tục' }).click();
    await expect(dialog).toBeHidden();
}

test('F&B Pilot can onboard, configure a multi-size menu item and open POS', async ({ page }) => {
    test.setTimeout(90_000);
    const configuredBaseUrl = test.info().project.use.baseURL;
    const targetHost = configuredBaseUrl ? new URL(configuredBaseUrl).hostname : '';
    expect(['127.0.0.1', 'localhost'], 'F&B fixture test refuses to mutate a non-local target.').toContain(targetHost);
    const suffix = Date.now().toString(36).toUpperCase();
    const categoryCode = `E2ECAT${suffix}`;
    const categoryName = `Đồ uống E2E ${suffix}`;
    const itemCode = `E2EITEM${suffix}`;
    const itemName = `Cà phê kiểm thử ${suffix}`;
    const apiFailures = [];
    const pageErrors = [];

    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('response', (response) => {
        if (response.url().includes('/admin/api/fnb') && response.status() >= 400 && response.status() !== 423) {
            apiFailures.push(`${response.status()} ${response.request().method()} ${response.url()}`);
        }
    });

    await loginAsAdmin(page);
    const scopeHeaders = { 'X-Website-Key': 'website-main', Accept: 'application/json' };
    const profileResponse = await page.request.get('/admin/api/me', { headers: scopeHeaders });
    expect(profileResponse.ok(), `Admin profile returned ${profileResponse.status()}`).toBeTruthy();
    const profile = (await profileResponse.json()).data;
    expect(profile.permissions).toContain('fnb.outlet.view');
    expect(profile.module_navigation?.some((item) => item.module_key === 'fnb-pos')).toBeTruthy();
    const bootstrapResponse = await page.request.get('/admin/api/fnb/bootstrap', { headers: scopeHeaders });
    expect(bootstrapResponse.ok(), `F&B bootstrap returned ${bootstrapResponse.status()}: ${await bootstrapResponse.text()}`).toBeTruthy();

    await page.locator('.admin-app-dropdown-trigger').click();
    await page.getByRole('menuitem').filter({ hasText: 'Quản lý quán Cafe' }).click();
    await expect(page.getByTestId('fnb-workspace')).toBeVisible();

    const onboardingSubmit = page.getByTestId('fnb-onboarding-submit');
    if (await onboardingSubmit.isVisible().catch(() => false)) {
        await page.getByLabel('Tên điểm bán').fill(`Cafe E2E ${suffix}`);
        await page.getByLabel('Mã điểm bán').fill(`Q${suffix}`);
        await onboardingSubmit.click();
        await completeReauthentication(page);
        await expect(page).toHaveURL(/\/admin\/modules\/fnb-pos\/dashboard/);
        await expect(page.getByText('Tổng quan vận hành')).toBeVisible();
    }

    await openFnbSection(page, 'settings');
    await expect(page.getByRole('heading', { name: 'Thiết bị, bàn, Bar và thanh toán' })).toBeVisible();
    await expect(page.getByText(/Thiết bị \(/)).toBeVisible();
    await expect(page.getByText(/Bàn \(5\)/)).toBeVisible();
    await expect(page.getByText(/Thanh toán \(2\)/)).toBeVisible();

    await openFnbSection(page, 'menu');
    await expect(page.getByRole('heading', { name: 'Món, size, topping và công thức' })).toBeVisible();
    await page.getByRole('tab', { name: /Nhóm món/ }).click();
    const addCategoryButton = page.getByRole('button', { name: 'Thêm nhóm' });
    await expect(addCategoryButton).toBeEnabled();
    await addCategoryButton.click();

    const categoryDrawer = page.getByRole('dialog', { name: 'Thêm nhóm món' });
    await expect(categoryDrawer).toBeVisible();
    await categoryDrawer.getByLabel('Mã').fill(categoryCode);
    await categoryDrawer.getByLabel('Tên nhóm').fill(categoryName);
    await categoryDrawer.getByRole('button', { name: 'Lưu nhóm' }).click();
    await expect(categoryDrawer).toBeHidden();
    await expect(page.getByText(categoryName, { exact: true })).toBeVisible();

    await page.getByRole('tab', { name: /Món & size/ }).click();
    await page.getByRole('button', { name: 'Thêm món' }).click();

    const itemDrawer = page.getByRole('dialog', { name: 'Thêm món mới' });
    await expect(itemDrawer).toBeVisible();
    await itemDrawer.getByLabel('Mã món').fill(itemCode);
    await itemDrawer.getByLabel('Tên món').fill(itemName);

    const categorySelect = itemDrawer.locator('.ant-form-item').filter({ hasText: 'Nhóm món' }).locator('.ant-select-selector');
    await categorySelect.click();
    await page.locator('.ant-select-dropdown:not(.ant-select-dropdown-hidden)').getByText(categoryName, { exact: true }).click();

    await itemDrawer.getByLabel('Mã size').first().fill('M');
    await itemDrawer.getByLabel('Tên size').first().fill('Vừa');
    await itemDrawer.getByLabel('Giá bán (VND)').first().fill('35000');
    await itemDrawer.getByRole('button', { name: 'Thêm size' }).click();
    await itemDrawer.getByLabel('Mã size').nth(1).fill('L');
    await itemDrawer.getByLabel('Tên size').nth(1).fill('Lớn');
    await itemDrawer.getByLabel('Giá bán (VND)').nth(1).fill('45000');
    await itemDrawer.getByRole('button', { name: 'Lưu món' }).click();

    await expect(itemDrawer).toBeHidden();
    const itemRow = page.getByRole('row').filter({ hasText: itemName });
    await expect(itemRow).toBeVisible();
    await expect(itemRow.getByText('Vừa', { exact: false })).toBeVisible();
    await expect(itemRow.getByText('Lớn', { exact: false })).toBeVisible();

    const initialBusinessDayRead = page.waitForResponse((response) => response.url().includes('/admin/api/fnb/outlets/') && response.url().includes('/business-days/current') && response.status() === 200);
    await openFnbSection(page, 'shifts');
    await initialBusinessDayRead;
    const openDayButton = page.getByRole('button', { name: 'Mở ngày hôm nay' });
    await expect(openDayButton.or(page.getByRole('button', { name: 'Đóng ngày' }))).toBeVisible();
    if (await openDayButton.isVisible().catch(() => false)) {
        await openDayButton.click();
        const openDayDrawer = page.getByRole('dialog', { name: 'Mở ngày kinh doanh' });
        await expect(openDayDrawer).toBeVisible();
        await openDayDrawer.getByRole('button', { name: 'Xác nhận' }).click();
        await expect(openDayDrawer).toBeHidden();
    }
    const openShiftButton = page.getByRole('button', { name: 'Mở ca', exact: true });
    const openShiftSummary = page.getByText(/^Ca #\d+ · mở /);
    await expect(openShiftButton.or(openShiftSummary)).toBeVisible();
    if (await openShiftButton.isVisible().catch(() => false)) {
        await openShiftButton.click();
        const openShiftDrawer = page.getByRole('dialog', { name: 'Mở ca tại quầy này' });
        await expect(openShiftDrawer).toBeVisible();
        await openShiftDrawer.getByRole('button', { name: 'Xác nhận' }).click();
        await expect(openShiftDrawer).toBeHidden();
    }

    await openFnbSection(page, 'pos');
    await expect(page.getByRole('heading', { name: 'Bán hàng' })).toBeVisible();
    const productTile = page.locator('button.fnb-product-tile').filter({ hasText: itemName });
    await expect(productTile).toBeVisible();

    await page.getByRole('button', { name: '+ Quầy', exact: true }).click();
    const sessionDrawer = page.getByRole('dialog', { name: 'Mở phiên phục vụ' });
    await expect(sessionDrawer).toBeVisible();
    await sessionDrawer.getByRole('button', { name: 'Mở phiên', exact: true }).click();
    await expect(sessionDrawer).toBeHidden();

    await expect(productTile).toBeEnabled();
    await productTile.click();
    const addItemDrawer = page.getByRole('dialog', { name: `Thêm ${itemName}` });
    await expect(addItemDrawer).toBeVisible();
    await addItemDrawer.getByRole('button', { name: 'Thêm vào order' }).click();
    await expect(addItemDrawer).toBeHidden();

    const orderLine = page.locator('.fnb-order-line').filter({ hasText: itemName });
    await expect(orderLine).toBeVisible();
    await orderLine.getByRole('button', { name: 'Sửa món' }).click();
    const editItemDrawer = page.getByRole('dialog', { name: `Sửa ${itemName}` });
    await expect(editItemDrawer).toBeVisible();
    await editItemDrawer.getByLabel('Số lượng').fill('2');
    await editItemDrawer.getByRole('button', { name: 'Lưu thay đổi' }).click();
    await expect(editItemDrawer).toBeHidden();
    await expect(orderLine).toContainText('2×');

    await expect(page.locator('.ant-message-notice')).toHaveCount(0);
    await page.screenshot({ path: 'test-results/fnb-pos-pilot.png', fullPage: true });

    await orderLine.getByRole('button', { name: 'Xóa món nháp' }).click();
    await page.getByRole('button', { name: 'Xóa', exact: true }).click();
    await expect(orderLine).toHaveCount(0);

    expect(apiFailures, `F&B API failures:\n${apiFailures.join('\n')}`).toEqual([]);
    expect(pageErrors, `Browser page errors:\n${pageErrors.join('\n')}`).toEqual([]);
});
