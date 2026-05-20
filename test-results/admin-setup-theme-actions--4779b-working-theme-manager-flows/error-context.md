# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: admin-setup-theme-actions.spec.js >> setup theme actions route into working theme manager flows
- Location: tests\browser\admin-setup-theme-actions.spec.js:25:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('dialog', { name: /quản lý ngôn ngữ/i })
Expected: visible
Timeout: 10000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 10000ms
  - waiting for getByRole('dialog', { name: /quản lý ngôn ngữ/i })

```

# Page snapshot

```yaml
- generic [ref=e4]:
  - banner [ref=e5]:
    - button "Build Mart 123" [ref=e7] [cursor=pointer]:
      - img "Build Mart 123" [ref=e8]
    - generic [ref=e11]:
      - button "Trang chủ" [ref=e13] [cursor=pointer]:
        - generic [ref=e15]: Trang chủ
      - link "Website" [ref=e18] [cursor=pointer]:
        - /url: /
        - img "home" [ref=e20]:
          - img [ref=e21]
        - generic [ref=e23]: Website
      - button "Tài khoản" [ref=e25] [cursor=pointer]:
        - img "more" [ref=e27]:
          - img [ref=e28]
        - generic [ref=e30]: Tài khoản
  - banner [ref=e31]:
    - menu [ref=e32]:
      - menuitem "dashboard Trang chủ" [ref=e33] [cursor=pointer]:
        - img "dashboard" [ref=e34]:
          - img [ref=e35]
        - text: Trang chủ
      - menuitem "appstore App Store" [ref=e37] [cursor=pointer]:
        - img "appstore" [ref=e38]:
          - img [ref=e39]
        - text: App Store
      - menuitem [disabled]:
        - img:
          - img
  - main [ref=e42]:
    - generic [ref=e45]:
      - generic [ref=e47]:
        - generic [ref=e48]:
          - generic [ref=e49]: Danh sách App
          - heading "Danh sách App đang bật" [level=3] [ref=e50]
        - generic [ref=e51]: 1 App đang hoạt động
      - button "read CMS CMS App Module quản lý nội dung, trang, bài viết, menu và media cho AIO Platform. Phiên bản v0.2.0" [ref=e54] [cursor=pointer]:
        - generic [ref=e57]:
          - img "read" [ref=e60]:
            - img [ref=e61]
          - generic [ref=e63]:
            - generic [ref=e64]:
              - heading "CMS" [level=4] [ref=e65]
              - generic [ref=e66]: CMS App
            - generic [ref=e67]: Module quản lý nội dung, trang, bài viết, menu và media cho AIO Platform.
            - generic [ref=e68]: Phiên bản v0.2.0
```

# Test source

```ts
  1  | import { expect, test } from '@playwright/test';
  2  | 
  3  | const adminUsername = process.env.PLAYWRIGHT_ADMIN_USERNAME || 'admin';
  4  | const adminPassword = process.env.PLAYWRIGHT_ADMIN_PASSWORD || 'password';
  5  | 
  6  | async function loginAsAdmin(page) {
  7  |     await page.goto('/vi');
  8  |     await page.locator('[data-open-auth-modal="login"]').first().click();
  9  |     await expect(page.locator('[data-th-modal-panel="login"]')).toBeVisible();
  10 |     await page.locator('[data-th-auth-form="login"] input[name="login"]').fill(adminUsername);
  11 |     await page.locator('[data-th-auth-form="login"] input[name="password"]').fill(adminPassword);
  12 |     await page.locator('[data-th-auth-form="login"] .th-modal-submit').click();
  13 |     await expect(page.locator('[data-th-modal-panel="login"]')).toBeHidden();
  14 | }
  15 | 
  16 | async function openSetupAction(page, menuName) {
  17 |     await page.goto('/admin/setup');
  18 |     const menuItem = page.getByRole('menuitem', { name: menuName });
  19 | 
  20 |     await expect(menuItem).toBeVisible();
  21 | 
  22 |     return menuItem;
  23 | }
  24 | 
  25 | test('setup theme actions route into working theme manager flows', async ({ page }) => {
  26 |     await loginAsAdmin(page);
  27 | 
  28 |     const directOpenActions = [
  29 |         { menuName: /quản lý ngôn ngữ/i, dialogName: /quản lý ngôn ngữ/i },
  30 |         { menuName: /bản dịch của theme/i, dialogName: /bản dịch của theme/i },
  31 |         { menuName: /bản dịch frontend/i, dialogName: /frontend translations/i },
  32 |         { menuName: /tạo data test/i, dialogName: /tạo data test/i },
  33 |         { menuName: /rebuild curated local demo/i, dialogName: /rebuild curated local demo/i },
  34 |     ];
  35 | 
  36 |     for (const action of directOpenActions) {
  37 |         const menuItem = await openSetupAction(page, action.menuName);
  38 |         await menuItem.click();
  39 | 
  40 |         await expect(page).toHaveURL(/\/admin\/themes/);
> 41 |         await expect(page.getByRole('dialog', { name: action.dialogName })).toBeVisible();
     |                                                                             ^ Error: expect(locator).toBeVisible() failed
  42 |     }
  43 | 
  44 |     const conditionalActions = [
  45 |         { menuName: /palette theme/i, dialogName: /palette theme/i },
  46 |         { menuName: /xóa data test/i, dialogName: /xóa data test/i },
  47 |     ];
  48 | 
  49 |     for (const action of conditionalActions) {
  50 |         const setupMenuItem = await openSetupAction(page, action.menuName);
  51 |         const isDisabled = (await setupMenuItem.getAttribute('aria-disabled')) === 'true';
  52 | 
  53 |         if (isDisabled) {
  54 |             await page.goto('/admin/themes');
  55 |             await expect(page.getByRole('menuitem', { name: action.menuName })).toHaveAttribute('aria-disabled', 'true');
  56 |             continue;
  57 |         }
  58 | 
  59 |         await setupMenuItem.click();
  60 |         await expect(page).toHaveURL(/\/admin\/themes/);
  61 |         await expect(page.getByRole('dialog', { name: action.dialogName })).toBeVisible();
  62 |     }
  63 | });
```