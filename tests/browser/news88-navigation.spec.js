import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

for (const mobile of [false, true]) {
    test(`NEWS88 three-level navigation on ${mobile ? 'mobile' : 'desktop'}`, async ({ page }) => {
        await page.setViewportSize({ width: mobile ? 390 : 1129, height: 882 });
        const html = execFileSync(process.env.PHP_BINARY || 'php', ['tests/Support/render-news88-editor.php', '--nested-menu'], { encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 });
        await page.route('**/*', route => new URL(route.request().url()).pathname === '/vi'
            ? route.fulfill({ contentType: 'text/html', body: html }) : route.abort());
        await page.goto('/vi');
        const nav = page.getByRole('navigation', { name: 'Menu chính' });
        if (mobile) {
            await expect(nav).toBeHidden();
            await page.getByRole('button', { name: 'Menu', exact: true }).click();
        }
        const parent = nav.getByRole('link', { name: 'Giới thiệu', exact: true });
        const toggle = nav.getByRole('button', { name: 'Giới thiệu — menu con' });
        const child = nav.getByRole('link', { name: 'Đội ngũ', exact: true });
        await expect(child).toBeHidden();
        if (mobile) await toggle.click();
        else await parent.hover();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(child).toBeVisible();
        const nestedToggle = nav.getByRole('button', { name: 'Đội ngũ — menu con' });
        if (mobile) await nestedToggle.click();
        else { await nestedToggle.focus(); await page.keyboard.press('Enter'); }
        const leaf = nav.getByRole('link', { name: 'Ban lãnh đạo' });
        await expect(leaf).toBeVisible();
        await expect(leaf).toHaveAttribute('target', '_blank');
        await expect(leaf).toHaveAttribute('rel', 'noopener noreferrer');
        const bounds = await leaf.boundingBox();
        expect(bounds.x).toBeGreaterThanOrEqual(0);
        expect(bounds.x + bounds.width).toBeLessThanOrEqual(mobile ? 390 : 1129);
        await leaf.focus();
        await page.keyboard.press('Escape');
        await expect(leaf).toBeHidden();
        await expect(nestedToggle).toBeFocused();
        await page.locator('.n88-topbar').click({ position: { x: 5, y: 5 } });
        if (mobile) {
            await expect(nav).toBeHidden();
            await page.getByRole('button', { name: 'Menu', exact: true }).click();
        }
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await parent.click();
        await expect(page).toHaveURL(/#about$/);
    });
}
