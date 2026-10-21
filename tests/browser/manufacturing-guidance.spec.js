import { expect, test } from '@playwright/test';

test.skip(process.env.PLAYWRIGHT_MANUFACTURING_FIXTURE !== '1', 'Chỉ dùng fixture Manufacturing cô lập.');

test('Admin logo returns home and manufacturing warnings offer accessible, permission-aware remediation', async ({ page }) => {
    expect(['localhost', '127.0.0.1']).toContain(new URL(test.info().project.use.baseURL).hostname);
    expect(process.env.MANUFACTURING_BROWSER_SECRET?.length).toBeGreaterThanOrEqual(32);
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    let missing = true;
    let canOpenStore = true;
    let paused = false;
    let failCheck = false;
    await page.route('**/admin/api/manufacturing/bootstrap*', async (route) => {
        if (failCheck) return route.fulfill({ status: 503, json: { message: 'Kiểm tra thử nghiệm thất bại.' } });
        const response = await route.fetch();
        const json = await response.json();
        json.data.readiness = { ...json.data.readiness, can_open_app_store: canOpenStore, purchasing: !missing,
            blockers: missing ? [{ code: 'purchasing', message: 'Cần cài và bật Mua hàng và nhà cung cấp để vận hành mua nguyên liệu FOB.' }] : [] };
        json.data.runtime = { ...json.data.runtime, paused };
        await route.fulfill({ response, json });
    });
    await page.goto(`/__manufacturing_fixture/login/${process.env.MANUFACTURING_BROWSER_SECRET}`);
    const warning = page.getByRole('alert').filter({ hasText: 'Cần hoàn tất thiết lập vận hành' });
    await expect(warning).toBeVisible();
    await expect(warning.locator('strong')).toHaveText('Mua hàng và nhà cung cấp');
    // Clicking the warning body works alongside its keyboard-accessible action.
    await warning.getByText('Cần hoàn tất thiết lập vận hành', { exact: true }).click();
    const modal = page.getByRole('dialog', { name: 'Hoàn tất thiết lập vận hành', exact: true });
    await expect(modal).toBeVisible();
    await expect(modal.getByText('Mở App Store và tìm ứng dụng Mua hàng và nhà cung cấp.')).toBeVisible();
    await expect(modal.locator('ol strong')).toHaveText('Mua hàng và nhà cung cấp');
    await expect(modal.getByRole('link', { name: 'Mở App Store', exact: true })).toHaveAttribute('href', '/admin/modules');
    await expect(modal).not.toHaveClass(/ant-zoom/);
    await expect(modal).toBeInViewport();
    await page.screenshot({ path: test.info().outputPath('manufacturing-remediation.png'), animations: 'disabled' });
    await modal.getByRole('button', { name: 'Đóng', exact: true }).focus();
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
    await warning.getByRole('button', { name: 'Xem cách khắc phục' }).focus();
    await page.keyboard.press('Enter');
    await expect(modal).toBeVisible();
    failCheck = true;
    await modal.getByRole('button', { name: 'Kiểm tra lại', exact: true }).click();
    await expect(modal.getByText('Chưa kiểm tra được trạng thái vận hành')).toBeVisible();
    await expect(modal.getByText('Đã hoàn tất các thiết lập được cảnh báo.')).toHaveCount(0);
    failCheck = false;
    missing = false;
    await modal.getByRole('button', { name: 'Kiểm tra lại', exact: true }).click();
    await expect(modal.getByText('Đã hoàn tất các thiết lập được cảnh báo.')).toBeVisible();
    await expect(warning).toHaveCount(0);
    await modal.getByRole('button', { name: 'Đóng', exact: true }).click();

    missing = true;
    canOpenStore = false;
    paused = true;
    await page.getByRole('button', { name: /Làm mới$/ }).click();
    await warning.getByRole('button', { name: 'Xem cách khắc phục' }).click();
    await expect(modal.getByText('Tài khoản hiện tại chưa có quyền truy cập App Store.', { exact: false })).toBeVisible();
    await expect(modal.getByRole('link', { name: 'Mở App Store', exact: true })).toHaveCount(0);
    await modal.getByRole('button', { name: 'Đóng', exact: true }).click();
    await page.getByRole('alert').filter({ hasText: 'Hệ thống đang tạm dừng ghi dữ liệu' }).click();
    const pauseModal = page.getByRole('dialog', { name: 'Khắc phục tạm dừng vận hành', exact: true });
    await expect(pauseModal).toBeVisible();
    await expect(pauseModal.getByRole('button', { name: 'Tiếp tục vận hành', exact: true })).toBeVisible();
    await pauseModal.getByRole('button', { name: 'Đóng', exact: true }).click();

    await page.getByRole('link', { name: 'Trang chủ Admin', exact: true }).click();
    await expect(page).not.toHaveURL(/\/modules\/manufacturing/);
    await expect(page).toHaveURL(/\/admin(?:\/dashboard)?\/?$/);
    expect(errors).toEqual([]);
});
