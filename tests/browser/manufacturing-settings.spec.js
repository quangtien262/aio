import { expect, test } from '@playwright/test';

test.skip(process.env.PLAYWRIGHT_MANUFACTURING_FIXTURE !== '1', 'Chỉ dùng fixture Manufacturing cô lập.');
const dialog = (page, name) => page.getByRole('dialog', { name, exact: true });
const submit = async (drawer) => { await drawer.getByRole('button', { name: 'Ghi nhận', exact: true }).click(); await expect(drawer).toBeHidden(); };
async function select(drawer, label, name) {
    await drawer.getByLabel(label, { exact: true }).focus();
    await drawer.getByLabel(label, { exact: true }).press('ArrowDown');
    await drawer.page().locator('.ant-select-dropdown:not(.ant-select-dropdown-hidden) .ant-select-item-option-content').filter({ hasText: name }).first().click();
}

test('settings organizes real factory data and preserves setup actions across desktop and mobile', async ({ page }) => {
    test.setTimeout(90_000);
    page.setDefaultTimeout(12_000);
    expect(['localhost', '127.0.0.1']).toContain(new URL(test.info().project.use.baseURL).hostname);
    expect(process.env.MANUFACTURING_BROWSER_SECRET?.length).toBeGreaterThanOrEqual(32);
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const suffix = Date.now().toString(36).toUpperCase();
    const companyName = `Công ty Cổ phần May mặc An Phú ${suffix}`;
    const factoryName = `Nhà máy may An Phú ${suffix}`;
    await page.setViewportSize({ width: 1047, height: 853 });
    await page.goto(`/__manufacturing_fixture/login/${process.env.MANUFACTURING_BROWSER_SECRET}`);
    await expect(page.getByRole('heading', { name: 'Thiết lập sản xuất', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Tạo công ty', exact: true }).click();
    const company = dialog(page, 'Tạo công ty sản xuất');
    await company.getByLabel('Mã công ty', { exact: true }).fill(`AP-${suffix}`);
    await company.getByLabel('Tên công ty', { exact: true }).fill(companyName);
    await submit(company);
    await page.getByRole('button', { name: 'Tạo nhà máy', exact: true }).click();
    const factory = dialog(page, 'Tạo nhà máy');
    await select(factory, 'Công ty', companyName);
    await expect(factory.getByLabel('Mã nhà máy', { exact: true })).toHaveValue('');
    await factory.getByLabel('Tên nhà máy', { exact: true }).fill(factoryName);
    await submit(factory);
    await expect(page.locator('.mfg-settings-context h4')).toHaveText(factoryName);
    await expect(page.locator('.mfg-settings-context p')).toContainText('NM');

    await page.getByRole('button', { name: 'Tạo phân xưởng', exact: true }).click();
    const workshop = dialog(page, 'Tạo phân xưởng');
    await workshop.getByLabel('Mã phân xưởng', { exact: true }).fill('PX01');
    await workshop.getByLabel('Tên phân xưởng', { exact: true }).fill('Phân xưởng may 01');
    await submit(workshop);
    await page.getByRole('button', { name: 'Tạo chuyền', exact: true }).click();
    const line = dialog(page, 'Tạo chuyền');
    await select(line, 'Phân xưởng', 'Phân xưởng may 01');
    await line.getByLabel('Mã chuyền', { exact: true }).fill('CM01');
    await line.getByLabel('Tên chuyền', { exact: true }).fill('Chuyền may 01');
    await line.getByLabel('Năng lực phút / ca', { exact: true }).fill('480');
    await submit(line);
    await expect(page.locator('.mfg-settings-workshop')).toContainText('Chuyền may 01');
    await expect(page.locator('.mfg-settings-capacity')).toContainText('480');

    await page.getByRole('tab', { name: /Nhân sự & quyền/ }).click();
    await expect(page.getByRole('heading', { name: 'Thành viên & phân quyền', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Phân quyền', exact: true }).click();
    await expect(dialog(page, 'Phân quyền thành viên')).toBeVisible();
    await dialog(page, 'Phân quyền thành viên').getByRole('button', { name: 'Đóng', exact: true }).click();

    await page.getByRole('tab', { name: /Ca & danh mục/ }).click();
    await page.getByRole('button', { name: 'Tạo ca', exact: true }).click();
    const shift = dialog(page, 'Tạo ca làm việc');
    await shift.getByLabel('Mã ca', { exact: true }).fill('HC');
    await shift.getByLabel('Tên ca', { exact: true }).fill('Ca hành chính');
    await shift.getByLabel('Giờ bắt đầu (HH:mm)', { exact: true }).fill('08:00');
    await shift.getByLabel('Giờ kết thúc (HH:mm)', { exact: true }).fill('17:00');
    await submit(shift);
    await expect(page.getByRole('cell', { name: 'Ca hành chính', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Tạm dừng ghi dữ liệu', exact: true }).click();
    await expect(dialog(page, 'Tạm dừng ghi dữ liệu sản xuất')).toBeVisible();
    await dialog(page, 'Tạm dừng ghi dữ liệu sản xuất').getByRole('button', { name: 'Đóng', exact: true }).click();
    await page.getByRole('tab', { name: /Nhật ký/ }).click();
    await expect(page.getByRole('heading', { name: 'Sự kiện tích hợp', exact: true })).toBeVisible();
    await page.getByRole('tab', { name: /Cơ cấu tổ chức/ }).click();
    await page.screenshot({ path: test.info().outputPath('settings-desktop.png'), fullPage: true, animations: 'disabled' });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByRole('button', { name: 'Tạo phân xưởng', exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await page.screenshot({ path: test.info().outputPath('settings-mobile.png'), fullPage: true, animations: 'disabled' });
    expect(errors).toEqual([]);
});
