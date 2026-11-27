/**
 * EXAMIS U-02 — «نتائجي» through the real browser and server: a synthetic portal student sees nothing before the
 * school publishes, sees the published annual result after the administrator publishes on «نشر النتائج السنوية», and
 * nothing again after the withdrawal; ar (RTL) and en (LTR) at 1366 / 820 / 390 px without page-level horizontal scroll;
 * the «نتائجي» sidebar entry only for the portal user.
 *
 * Needs the U-01 fixture and the portal user written by D:\Projects\sis-examis-lab\scripts\e2e_u01_fixture.php to a
 * disposable sis_e2e_examis_* copy; skipped elsewhere.
 */
import fs from 'node:fs';
import path from 'node:path';
import type { Page } from '@playwright/test';
import { dbValue } from '../support/db';
import { BASE_URL, E2E_DB, RUN, personaPassword } from '../support/env';
import { expect, newPersonaContext, test } from '../support/test';
import { expectNotice, openDialog } from '../support/ui';

type Fixture = { database: string; academic_year_id: number; class_id: number; section_id: number };
type Portal = { email: string; enrollment_id: number };

const DIR = path.join('storage', 'app', 'e2e');

async function signIn(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(personaPassword());
    await page.locator('[data-test="login-button"]').click();
    await expect(page).not.toHaveURL(/\/login(\?|$)/, { timeout: 30_000 });
}

test.describe('my results (U-02)', () => {
    test.describe.configure({ mode: 'serial' });
    test.skip(!E2E_DB.startsWith('sis_e2e_examis_'), 'needs the U-01/U-02 fixture on a sis_e2e_examis_* copy');

    let f: Fixture;
    let portal: Portal;
    test.beforeAll(() => {
        f = JSON.parse(fs.readFileSync(path.join(DIR, 'u01-fixture.json'), 'utf8')) as Fixture;
        portal = JSON.parse(fs.readFileSync(path.join(DIR, 'u02-portal.json'), 'utf8')) as Portal;
        expect(f.database).toBe(E2E_DB);
    });

    const published = () =>
        Number(dbValue("SELECT count(*) FROM results.result_publications WHERE class_id = ? AND section_id = ? AND kind = 'annual' AND status = 'published'", [f.class_id, f.section_id]));

    test('publish, the student sees it in ar and en at three widths, withdraw, the student sees nothing', async ({ page, browser }) => {
        // The administrator (default storage state) publishes the complete section on the U-01 page.
        expect(published()).toBe(0);
        const student = await browser.newContext({ storageState: { cookies: [], origins: [] } });
        const sp = await student.newPage();
        await signIn(sp, portal.email);
        await sp.goto('/portal/results');
        await expect(sp.getByText('لا توجد نتائج منشورة', { exact: true })).toBeVisible();
        await expect(sp.getByRole('link', { name: 'نتائجي' }).first()).toBeVisible();

        // The entry follows the portal permission (computed on the server): the teacher persona has none.
        const teacher = await newPersonaContext(browser, 'teacher');
        const tp = await teacher.newPage();
        await tp.goto('/dashboard');
        await expect(tp.locator('[data-sidebar="sidebar"]').first()).toBeAttached();
        await expect(tp.getByRole('link', { name: 'نتائجي' })).toHaveCount(0);
        await teacher.close();

        await page.goto(`/results/publications?academic_year_id=${f.academic_year_id}&class_id=${f.class_id}&section_id=${f.section_id}`);
        await page.getByRole('button', { name: 'نشر النتائج السنوية' }).click();
        await page.getByRole('alertdialog').or(page.getByRole('dialog')).filter({ hasText: 'نشر النتائج للطلبة؟' }).last()
            .getByRole('button', { name: 'نشر النتائج السنوية' }).click();
        await expectNotice(page, 'نُشرت النتائج السنوية للطلبة.');
        await expect.poll(published).toBe(1);

        for (const [lang, dir, heading] of [['ar', 'rtl', 'نتائجي'], ['en', 'ltr', 'My results']] as const) {
            await student.addCookies([{ name: 'locale', value: lang, url: BASE_URL }]);
            for (const width of [1366, 820, 390]) {
                await sp.setViewportSize({ width, height: 900 });
                await sp.goto('/portal/results');
                await expect(sp.locator('html')).toHaveAttribute('dir', dir);
                await expect(sp.getByRole('heading', { level: 1 })).toHaveText(heading);
                await expect(sp.locator('section[aria-labelledby^="portal-result-"]')).toHaveCount(1);
                const overflow = await sp.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
                expect(overflow, `horizontal page scroll at ${width}px (${lang})`).toBeLessThanOrEqual(1);
            }
        }
        await student.addCookies([{ name: 'locale', value: 'ar', url: BASE_URL }]);

        await page.goto(`/results/publications?academic_year_id=${f.academic_year_id}&class_id=${f.class_id}&section_id=${f.section_id}`);
        await page.getByRole('button', { name: /^سحب النشر: النتائج السنوية/ }).first().click();
        const dialog = openDialog(page, 'سحب نشر النتائج');
        await dialog.getByRole('textbox').fill(`اختبار متصفح U-02 ${RUN}`);
        await dialog.getByRole('button', { name: 'سحب النشر', exact: true }).click();
        await expectNotice(page, 'سُحب نشر النتائج.');
        await expect.poll(published).toBe(0);

        await sp.goto('/portal/results');
        await expect(sp.getByText('لا توجد نتائج منشورة', { exact: true })).toBeVisible();
        await student.close();
    });

    test('a request for another student changes nothing', async ({ browser }) => {
        const student = await browser.newContext({ storageState: { cookies: [], origins: [] } });
        const sp = await student.newPage();
        await signIn(sp, portal.email);
        const other = Number(dbValue('SELECT id FROM enrollment.enrollments WHERE section_id = ? AND id <> ? ORDER BY id LIMIT 1', [f.section_id, portal.enrollment_id]));
        await sp.goto(`/portal/results?enrollment_id=${other}&student_id=${other}`);
        await expect(sp.locator('section[aria-labelledby^="portal-result-"]')).toHaveCount(0);
        await student.close();
    });
});
