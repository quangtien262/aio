import { expect, test } from '@playwright/test';

test.skip(process.env.PLAYWRIGHT_MANUFACTURING_FIXTURE !== '1', 'Chỉ ghi vào fixture Manufacturing cô lập.');

test('demand matrix table preserves rows, validation and saved variants on desktop and mobile', async ({ page }) => {
    test.setTimeout(120_000);
    expect(['localhost', '127.0.0.1']).toContain(new URL(test.info().project.use.baseURL).hostname);
    expect(process.env.MANUFACTURING_BROWSER_SECRET?.length).toBeGreaterThanOrEqual(32);
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const suffix = Date.now().toString(36).toUpperCase();
    await page.setViewportSize({ width: 1047, height: 853 });
    await page.goto(`/__manufacturing_fixture/login/${process.env.MANUFACTURING_BROWSER_SECRET}`);
    await expect(page.getByRole('heading', { name: 'Thiết lập sản xuất', exact: true })).toBeVisible();
    const boot = await (await page.request.get('/admin/api/manufacturing/bootstrap')).json();
    const factoryId = boot.data.current_factory_id;
    expect(factoryId).toBeTruthy();
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    let sequence = 0;
    const command = async (operation, data, resource = null) => {
        const response = await page.request.post(`/admin/api/manufacturing/commands/${operation}`, {
            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Idempotency-Key': `matrix-${suffix}-${++sequence}` },
            data: { factory_id: factoryId, resource, data },
        });
        expect(response.ok(), operation).toBeTruthy();
        return (await response.json()).data;
    };
    await command('sku.save', { sku: `FABRIC-${suffix}`, name: 'Vải kiểm thử', base_unit: 'm', tracking_mode: 'batch' });
    let style = await command('style.save', { code: `SHIRT-${suffix}`, name: 'Áo sơ mi', sizes: ['S', 'M'], colors: ['Đen', 'Trắng'], bom: [{ material_code: `FABRIC-${suffix}`, uom: 'm', quantity: 1 }], routing: [{ code: 'SEW', name: 'May', smv: 5 }] });
    const sample = await command('sample.save', { style_id: style.id, type: 'pp' });
    await command('sample.approve', { expected_version: sample.version }, sample.id);
    await command('style.approve', { expected_version: style.version }, style.id);

    await page.goto('/admin/modules/manufacturing/demands');
    await page.getByRole('button', { name: 'Lưu nhu cầu', exact: true }).click();
    const drawer = page.getByRole('dialog', { name: 'Lưu nhu cầu', exact: true });
    await drawer.getByLabel('Mã nhu cầu', { exact: true }).fill(`DEMAND-${suffix}`);
    await drawer.getByLabel('Ngày cần hàng', { exact: true }).fill('2026-11-30');
    await drawer.getByLabel('Mã hàng', { exact: true }).fill(`SHIRT-${suffix}`);
    await page.locator('.ant-select-dropdown:not(.ant-select-dropdown-hidden) .ant-select-item-option-content').filter({ hasText: `SHIRT-${suffix}` }).click();
    const matrix = drawer.locator('.mfg-form-list').filter({ has: page.getByRole('heading', { name: 'Số lượng theo màu / kích cỡ', exact: true }) });
    const table = matrix.getByRole('table', { name: 'Số lượng theo màu / kích cỡ', exact: true });
    await expect(table.getByRole('columnheader')).toHaveCount(5);
    const tableRows = table.locator('tbody tr');
    await matrix.getByRole('button', { name: '+ Thêm dòng', exact: true }).click();
    await tableRows.nth(0).getByLabel('Màu', { exact: true }).fill('Đen');
    await tableRows.nth(0).getByLabel('Kích cỡ', { exact: true }).fill('S');
    await drawer.getByRole('button', { name: 'Ghi nhận', exact: true }).click();
    await expect(tableRows.nth(0).getByText('Vui lòng nhập số lượng.', { exact: true })).toBeVisible();
    await tableRows.nth(0).getByLabel('Số lượng', { exact: true }).fill('10');
    await matrix.getByRole('button', { name: '+ Thêm dòng', exact: true }).click();
    await tableRows.nth(1).getByLabel('Màu', { exact: true }).fill('Bỏ dòng này');
    await tableRows.nth(1).getByLabel('Kích cỡ', { exact: true }).fill('XL');
    await tableRows.nth(1).getByLabel('Số lượng', { exact: true }).fill('999');
    await matrix.getByRole('button', { name: '+ Thêm dòng', exact: true }).click();
    await tableRows.nth(2).getByLabel('Màu', { exact: true }).fill('Trắng');
    await tableRows.nth(2).getByLabel('Kích cỡ', { exact: true }).fill('M');
    await tableRows.nth(2).getByLabel('Số lượng', { exact: true }).fill('20');
    await table.getByRole('button', { name: 'Xóa dòng 2', exact: true }).click();
    await expect(tableRows).toHaveCount(2);
    await expect(tableRows.nth(1).getByLabel('Màu', { exact: true })).toHaveValue('Trắng');
    await expect(tableRows.nth(1).getByLabel('Số lượng', { exact: true })).toHaveValue('20');
    await matrix.scrollIntoViewIfNeeded();
    await page.screenshot({ path: test.info().outputPath('demand-table-desktop.png'), animations: 'disabled' });
    const saved = page.waitForResponse((response) => response.request().method() === 'POST' && response.url().endsWith('/commands/demand.save'));
    await drawer.getByRole('button', { name: 'Ghi nhận', exact: true }).click();
    const response = await saved;
    expect(response.ok()).toBeTruthy();
    expect(response.request().postDataJSON().data.lines).toEqual([{ color: 'Đen', size: 'S', quantity: 10 }, { color: 'Trắng', size: 'M', quantity: 20 }]);
    await expect(drawer).toBeHidden();
    await page.reload();
    await page.getByRole('row').filter({ hasText: `DEMAND-${suffix}` }).getByRole('button', { name: 'Sửa', exact: true }).click();
    await expect(tableRows).toHaveCount(2);
    await expect(tableRows.nth(0).getByLabel('Số lượng', { exact: true })).toHaveValue('10');
    await expect(tableRows.nth(1).getByLabel('Màu', { exact: true })).toHaveValue('Trắng');
    await page.setViewportSize({ width: 390, height: 844 });
    const region = matrix.getByRole('region', { name: 'Số lượng theo màu / kích cỡ', exact: true });
    await region.scrollIntoViewIfNeeded();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    expect(await region.evaluate((node) => node.scrollWidth > node.clientWidth)).toBe(true);
    await page.screenshot({ path: test.info().outputPath('demand-table-mobile.png'), animations: 'disabled' });
    await drawer.getByRole('button', { name: 'Đóng', exact: true }).click();
    await page.goto('/admin/modules/manufacturing/planning');
    await page.getByRole('button', { name: 'Lập lệnh sản xuất', exact: true }).click();
    await expect(page.getByRole('dialog', { name: 'Lập lệnh sản xuất', exact: true }).getByRole('table', { name: 'Số lượng theo màu / kích cỡ', exact: true })).toBeVisible();
    expect(errors).toEqual([]);
});
