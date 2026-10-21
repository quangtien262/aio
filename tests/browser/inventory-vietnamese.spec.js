import { expect, test } from '@playwright/test';

test.skip(process.env.PLAYWRIGHT_MANUFACTURING_FIXTURE !== '1', 'Chỉ dùng cơ sở dữ liệu thử nghiệm cô lập.');

test('inventory uses Vietnamese labels across pages, forms, empty states and sync reports', async ({ page }) => {
    test.setTimeout(90_000);
    page.setDefaultTimeout(10_000);
    expect(['localhost', '127.0.0.1']).toContain(new URL(test.info().project.use.baseURL).hostname);
    expect(process.env.MANUFACTURING_BROWSER_SECRET?.length).toBeGreaterThanOrEqual(32);
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.setViewportSize({ width: 819, height: 853 });
    await page.goto(`/__manufacturing_fixture/login/${process.env.MANUFACTURING_BROWSER_SECRET}`);
    await expect(page.getByRole('heading', { name: 'Thiết lập sản xuất', exact: true })).toBeVisible();
    const factory = (await (await page.request.get('/admin/api/manufacturing/bootstrap')).json()).data.current_factory_id;
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const received = await page.request.post('/admin/api/manufacturing/commands/material.receive', {
        headers: { 'X-CSRF-TOKEN': csrf, 'Idempotency-Key': `inventory-labels-${Date.now()}`, Accept: 'application/json' },
        data: { factory_id: factory, data: { name: 'Vải kiểm tra tiếng Việt', uom: 'm', quantity: '2.5', unit_cost: '100', reason: 'Tồn đầu kỳ đã kiểm đếm' } },
    });
    expect(received.ok()).toBeTruthy();
    for (const [path, title] of [['dashboard', 'Tổng quan kho'], ['items', 'Hàng hóa'], ['warehouses', 'Kho'], ['locations', 'Vị trí kho'], ['replenishment', 'Bổ sung hàng'], ['batches', 'Lô & số sê-ri'], ['balances', 'Tồn kho'], ['documents', 'Phiếu kho'], ['movements', 'Lịch sử kho']]) {
        await page.goto(`/admin/inventory/${path}`);
        await expect(page.locator('.inventory-workspace')).toBeVisible();
        await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
        const text = await page.locator('.inventory-workspace').innerText();
        expect(text).not.toMatch(/Hang hoa|Dong bo san pham|Trang thai|Tac vu|Nhap kho|No data|Chua co|Sync gan nhat/);
        expect(text).not.toMatch(/\uFFFD|Ã¡|á»|áº/);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    }
    await page.goto('/admin/inventory/items');
    await page.getByRole('button', { name: 'Cấu hình', exact: true }).first().click();
    let form = page.getByRole('dialog');
    await expect(form.getByText('Đơn vị tính', { exact: true })).toBeVisible();
    await expect(form.getByText('Theo dõi lô', { exact: true })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(form).toBeHidden();
    await page.route('**/admin/api/inventory/items/sync-products', (route) => route.fulfill({ json: { data: { created_count: 1, updated_count: 0, skipped_count: 0, failed_count: 0, lines: [{ id: 1, name: 'Áo sơ mi', sku: 'AO-01', action: 'created', message: 'Đã tạo hàng hóa mới từ sản phẩm Catalog.' }] } } }));
    await page.getByRole('button', { name: /Đồng bộ sản phẩm/ }).click();
    const report = page.getByRole('dialog', { name: 'Báo cáo đồng bộ sản phẩm', exact: true });
    await expect(report.getByRole('columnheader', { name: 'Kết quả', exact: true })).toBeVisible();
    await expect(report.getByRole('row').filter({ hasText: 'Áo sơ mi' })).toContainText('Tạo mới');
    await report.getByRole('button', { name: 'Đóng', exact: true }).click();
    await expect(report).toBeHidden();
    await page.locator('.inventory-workspace .ant-table-content').evaluateAll((nodes) => nodes.forEach((node) => { node.scrollLeft = 0; }));
    await page.screenshot({ path: test.info().outputPath('inventory-items-819.png'), animations: 'disabled' });
    await page.goto('/admin/inventory/warehouses');
    await page.getByRole('button', { name: /Thêm kho/ }).click();
    form = page.getByRole('dialog', { name: /Thêm kho/ });
    await form.getByRole('button', { name: 'Lưu', exact: true }).click();
    await expect(form.getByText('Nhập tên kho', { exact: true })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(form).toBeHidden();
    await page.goto('/admin/inventory/locations');
    await page.getByRole('button', { name: /Thêm vị trí/ }).click();
    form = page.getByRole('dialog', { name: 'Thêm vị trí kho', exact: true });
    await expect(form.getByText('Loại vị trí', { exact: true })).toBeVisible();
    await expect(form.locator('.ant-select-selection-item').filter({ hasText: 'Lưu trữ' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(form).toBeHidden();
    await page.goto('/admin/inventory/documents');
    await page.getByRole('button', { name: /Tạo phiếu/ }).click();
    form = page.getByRole('dialog', { name: 'Tạo phiếu kho', exact: true });
    await expect(form.getByRole('button', { name: 'Ghi sổ phiếu', exact: true })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(form.getByText('Loại phiếu', { exact: true })).toBeVisible();
    await page.screenshot({ path: test.info().outputPath('inventory-document-mobile.png'), animations: 'disabled' });
    await page.keyboard.press('Escape');
    await expect(form).toBeHidden();
    await page.goto('/admin/inventory/replenishment');
    await expect(page.getByText('Chưa có dữ liệu.', { exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    expect(errors).toEqual([]);
});
