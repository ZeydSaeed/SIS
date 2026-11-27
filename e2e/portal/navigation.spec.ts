/**
 * EXAMIS R-09 — a portal-only account (synthetic student) sees «نتائجي» and no staff module, in the sidebar and on the
 * dashboard; a staff persona (teacher) keeps its modules. Needs the portal user written by
 * D:\Projects\sis-examis-lab\scripts\e2e_u01_fixture.php (portal-user) on a disposable sis_e2e_examis_* copy.
 */
import fs from 'node:fs';
import path from 'node:path';
import type { BrowserContextOptions } from '@playwright/test';
import { authFile, BASE_URL, E2E_DB, personaPassword } from '../support/env';
import { expect, test } from '../support/test';

type Portal = { email: string };

test.describe('portal-only navigation (R-09)', () => {
    test.skip(!E2E_DB.startsWith('sis_e2e_examis_'), 'needs the portal user on a sis_e2e_examis_* copy');

    test('@responsive the student sees «نتائجي» only; the teacher keeps the staff modules', async ({ browser }) => {
        const portal = JSON.parse(fs.readFileSync(path.join('storage', 'app', 'e2e', 'u02-portal.json'), 'utf8')) as Portal;
        // The project's device (viewport, touch, phone UA): a context made by hand does not inherit it.
        const u = test.info().project.use;
        const device: BrowserContextOptions = {
            baseURL: BASE_URL, viewport: u.viewport, isMobile: u.isMobile, hasTouch: u.hasTouch, userAgent: u.userAgent, deviceScaleFactor: u.deviceScaleFactor,
        };
        const context = await browser.newContext({ ...device, storageState: { cookies: [], origins: [] } });
        const page = await context.newPage();
        await page.goto('/login');
        await page.locator('input[name="email"]').fill(portal.email);
        await page.locator('input[name="password"]').fill(personaPassword());
        await page.locator('[data-test="login-button"]').click();
        await expect(page).not.toHaveURL(/\/login(\?|$)/, { timeout: 30_000 });

        // On a phone the sidebar is an off-canvas sheet, rendered only when opened: checked from tablet width up.
        const wide = (page.viewportSize()?.width ?? 0) >= 768;
        await page.goto('/dashboard');
        if (wide) {
            const sidebar = page.locator('[data-sidebar="content"]').first();
            await expect(sidebar).toBeAttached();
            await expect(sidebar.getByRole('link')).toHaveCount(1);
            await expect(sidebar.getByRole('link', { name: 'نتائجي' })).toHaveCount(1);
        }
        const tiles = page.locator('.sis-dashboard-launch').getByRole('link');
        await expect(tiles).toHaveCount(1);
        await expect(tiles.first()).toHaveAttribute('href', /\/portal\/results$/);
        await context.close();

        const teacher = await browser.newContext({ ...device, storageState: authFile('teacher') });
        const tp = await teacher.newPage();
        await tp.goto('/dashboard');
        expect(await tp.locator('.sis-dashboard-launch').getByRole('link').count()).toBeGreaterThan(5);
        if (wide) {
            const staffSidebar = tp.locator('[data-sidebar="content"]').first();
            await expect(staffSidebar).toBeAttached();
            expect(await staffSidebar.getByRole('link').count()).toBeGreaterThan(5);
            await expect(staffSidebar.getByRole('link', { name: 'نتائجي' })).toHaveCount(0);
        }
        await teacher.close();
    });
});
