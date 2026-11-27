/**
 * EXAMIS U-03 — «نشر نتائج الفصول» and the transcripts on «نشر النتائج السنوية», through the real browser and server:
 * publish → the portal student sees it on «نتائجي» → withdraw → the student sees nothing; the pages in ar (RTL) and
 * en (LTR) without page-level horizontal scroll at 1366 / 820 / 390 px (desktop project) and on the tablet and phone
 * projects (@responsive).
 *
 * Needs the fixtures written by D:\Projects\sis-examis-lab\scripts\e2e_u01_fixture.php (term, transcript, portal-user)
 * on a disposable sis_e2e_examis_* copy; skipped elsewhere.
 */
import fs from 'node:fs';
import path from 'node:path';
import type { Browser, Page, TestInfo } from '@playwright/test';
import { dbValue } from '../support/db';
import { BASE_URL, E2E_DB, RUN, personaPassword } from '../support/env';
import { expect, test } from '../support/test';
import { expectNotice, openDialog } from '../support/ui';

type Term = { database: string; academic_year_id: number; term_id: number; subject_id: number; class_id: number; section_id: number; enrollment_ids: number[] };
type Scope = { database: string; academic_year_id: number; class_id: number; section_id: number };
type Portal = { email: string; enrollment_id: number };

const DIR = path.join('storage', 'app', 'e2e');
const read = <T>(file: string): T => JSON.parse(fs.readFileSync(path.join(DIR, file), 'utf8')) as T;

async function student(browser: Browser, email: string): Promise<{ page: Page; close: () => Promise<void> }> {
    const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const page = await context.newPage();
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(personaPassword());
    await page.locator('[data-test="login-button"]').click();
    await expect(page).not.toHaveURL(/\/login(\?|$)/, { timeout: 30_000 });

    return { page, close: () => context.close() };
}

/** No page-level horizontal scroll, in ar and en; the desktop project also checks 820 and 390 px. */
async function responsive(page: Page, url: string, heading: { ar: string; en: string }, info: TestInfo): Promise<void> {
    const widths = info.project.name === 'chromium' ? [1366, 820, 390] : [null];
    for (const [lang, dir] of [['ar', 'rtl'], ['en', 'ltr']] as const) {
        await page.context().addCookies([{ name: 'locale', value: lang, url: BASE_URL }]);
        for (const width of widths) {
            if (width !== null) {
                await page.setViewportSize({ width, height: 900 });
            }
            await page.goto(url);
            await expect(page.locator('html')).toHaveAttribute('dir', dir);
            await expect(page.getByRole('heading', { level: 1 })).toHaveText(heading[lang]);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow, `horizontal page scroll (${lang}, ${width ?? info.project.name})`).toBeLessThanOrEqual(1);
        }
    }
    await page.context().addCookies([{ name: 'locale', value: 'ar', url: BASE_URL }]);
}

test.describe('term and transcript publications (U-03)', () => {
    test.describe.configure({ mode: 'serial' });
    test.skip(!E2E_DB.startsWith('sis_e2e_examis_'), 'needs the U-03 fixtures on a sis_e2e_examis_* copy');

    let term: Term;
    let tr: Scope;
    let portal: Portal;
    test.beforeAll(() => {
        term = read<Term>('u03-term.json');
        tr = read<Scope>('u03-transcript.json');
        portal = read<Portal>('u02-portal.json');
        expect([term.database, tr.database]).toEqual([E2E_DB, E2E_DB]);
        expect(term.enrollment_ids).toContain(portal.enrollment_id);
    });

    const termUrl = () =>
        `/results/term-publications?academic_year_id=${term.academic_year_id}&term_id=${term.term_id}&subject_id=${term.subject_id}&class_id=${term.class_id}&section_id=${term.section_id}`;
    const yearUrl = () => `/results/publications?academic_year_id=${tr.academic_year_id}&class_id=${tr.class_id}&section_id=${tr.section_id}`;
    const count = (kind: 'term' | 'transcript') =>
        Number(dbValue('SELECT count(*) FROM results.result_publications WHERE class_id = ? AND kind = ? AND status = ?', [kind === 'term' ? term.class_id : tr.class_id, kind, 'published']));

    test('@responsive the term publication page renders without page-level horizontal scroll', async ({ page }, info) => {
        await responsive(page, termUrl(), { ar: 'نشر نتائج الفصول', en: 'Publish term results' }, info);
    });

    test('@responsive the year publication page with transcripts renders without page-level horizontal scroll', async ({ page }, info) => {
        await responsive(page, yearUrl(), { ar: 'نشر النتائج السنوية', en: 'Publish annual results' }, info);
    });

    test('the whole class is not ready for the term; the seated section is published, shown to the student, then withdrawn', async ({ page, browser }) => {
        await page.goto(termUrl().replace(`&section_id=${term.section_id}`, ''));
        // Section B students are seated too but have no official term result: the whole class is not ready.
        await expect(page.getByRole('button', { name: 'نشر نتائج الفصل' })).toBeDisabled();
        expect(count('term')).toBe(0);

        await page.goto(termUrl());
        await expect(page.getByRole('combobox', { name: 'الفصل الدراسي' })).toBeVisible();
        await expect(page.getByRole('table')).toBeVisible();
        await page.getByRole('button', { name: 'نشر نتائج الفصل' }).click();
        await page.getByRole('alertdialog').or(page.getByRole('dialog')).filter({ hasText: 'نشر النتائج للطلبة؟' }).last()
            .getByRole('button', { name: 'نشر نتائج الفصل' }).click();
        await expectNotice(page, 'نُشرت نتائج الفصل للطلبة.');
        await expect.poll(() => count('term')).toBe(1);

        const s = await student(browser, portal.email);
        await s.page.goto('/portal/results');
        await expect(s.page.getByRole('table', { name: 'نتائج الفصول' })).toBeVisible();

        await page.goto(termUrl());
        await page.getByRole('button', { name: /^سحب النشر: نتائج/ }).first().click();
        const dialog = openDialog(page, 'سحب نشر النتائج');
        await dialog.getByRole('textbox').fill(`اختبار متصفح U-03 فصل ${RUN}`);
        await dialog.getByRole('button', { name: 'سحب النشر', exact: true }).click();
        await expectNotice(page, 'سُحب نشر النتائج.');
        await expect.poll(() => count('term')).toBe(0);

        await s.page.goto('/portal/results');
        await expect(s.page.getByRole('table', { name: 'نتائج الفصول' })).toHaveCount(0);
        await s.close();
    });

    test('transcripts: published from the year page, shown with their number to the student, then withdrawn', async ({ page, browser }) => {
        const number = String(dbValue('SELECT transcript_number FROM results.transcripts WHERE enrollment_id = ? AND is_current', [portal.enrollment_id]));
        expect(count('transcript')).toBe(0);

        await page.goto(yearUrl());
        await expect(page.getByRole('cell', { name: number })).toBeVisible();
        await page.getByRole('button', { name: 'نشر كشوف الدرجات' }).click();
        await page.getByRole('alertdialog').or(page.getByRole('dialog')).filter({ hasText: 'نشر النتائج للطلبة؟' }).last()
            .getByRole('button', { name: 'نشر كشوف الدرجات' }).click();
        await expectNotice(page, 'نُشرت كشوف الدرجات للطلبة.');
        await expect.poll(() => count('transcript')).toBe(1);

        const s = await student(browser, portal.email);
        await s.page.goto('/portal/results');
        await expect(s.page.getByText(number, { exact: true })).toBeVisible();

        await page.goto(yearUrl());
        await page.getByRole('button', { name: /^سحب النشر: كشوف الدرجات/ }).first().click();
        const dialog = openDialog(page, 'سحب نشر النتائج');
        await dialog.getByRole('textbox').fill(`اختبار متصفح U-03 كشوف ${RUN}`);
        await dialog.getByRole('button', { name: 'سحب النشر', exact: true }).click();
        await expectNotice(page, 'سُحب نشر النتائج.');
        await expect.poll(() => count('transcript')).toBe(0);

        await s.page.goto('/portal/results');
        await expect(s.page.getByText(number, { exact: true })).toHaveCount(0);
        await s.close();
    });
});
