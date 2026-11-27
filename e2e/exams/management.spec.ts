/**
 * EXAMIS 7.2 — managing exams and sessions from the web through the real browser and server, with the commands of the
 * «تحرير» ribbon: the manager creates an exam, adds a session, starts and closes it; edits the exam, adds, edits and
 * cancels a second session; cancelling the exam is refused once a session is completed; a viewer gets no management
 * command; the pages in ar / en without page-level horizontal scroll (@responsive).
 *
 * Needs an exam type, which only `php artisan sis:e2e-seed-exams` provides (disposable sis_e2e_* copy); skipped on
 * sis_e2e. Seating and the roster («كشف المرشحين») are unit 7.5 — see e2e/exams/administration.spec.ts.
 */
import fs from 'node:fs';
import path from 'node:path';
import type { Page, TestInfo } from '@playwright/test';
import { dbValue } from '../support/db';
import { BASE_URL, E2E_DB, RUN } from '../support/env';
import { command, editTab } from '../support/examis';
import { expect, test } from '../support/test';
import { choose, expectNotice, field, openDialog } from '../support/ui';

type Fixture = { database: string; academic_year_id: number };

const MANIFEST = path.join('storage', 'app', 'e2e', 'exam-fixture.json');

async function noPageScroll(page: Page, url: string, info: TestInfo): Promise<void> {
    const widths = info.project.name === 'chromium' ? [1366, 820, 390] : [null];
    for (const [lang, dir] of [['ar', 'rtl'], ['en', 'ltr']] as const) {
        await page.context().addCookies([{ name: 'locale', value: lang, url: BASE_URL }]);
        for (const width of widths) {
            if (width !== null) {
                await page.setViewportSize({ width, height: 900 });
            }
            await page.goto(url);
            await expect(page.locator('html')).toHaveAttribute('dir', dir);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow, `horizontal page scroll (${lang}, ${width ?? info.project.name}) on ${url}`).toBeLessThanOrEqual(1);
        }
    }
    await page.context().addCookies([{ name: 'locale', value: 'ar', url: BASE_URL }]);
}

test.describe('exam and session management (7.2)', () => {
    test.describe.configure({ mode: 'serial' });
    test.skip(E2E_DB === 'sis_e2e', 'exam types are only seeded on a disposable sis_e2e_* copy (php artisan sis:e2e-seed-exams)');

    let f: Fixture;
    const examName = `امتحان إدارة من الواجهة ${RUN}`;
    const examId = () => Number(dbValue('SELECT id FROM exams.exams WHERE name LIKE ? ORDER BY id DESC LIMIT 1', [`${examName}%`]));
    test.beforeAll(() => {
        f = JSON.parse(fs.readFileSync(MANIFEST, 'utf8')) as Fixture;
        expect(f.database, `${MANIFEST} belongs to another database`).toBe(E2E_DB);
    });

    test('the manager creates an exam, adds a session, starts and closes it', async ({ page }) => {
        await page.goto(`/exams?academic_year_id=${f.academic_year_id}`);
        await command(page, 'امتحان جديد');
        const create = openDialog(page, 'امتحان جديد');
        await field(create, 'اسم الامتحان').fill(examName);
        await choose(page, create, 'الفصل', /./);
        await choose(page, create, 'نوع الامتحان', /./);
        await field(create, 'تاريخ البداية').fill('2026-12-01');
        await field(create, 'تاريخ النهاية').fill('2026-12-10');
        await create.getByRole('button', { name: 'إنشاء الامتحان' }).click();
        await expect(page).toHaveURL(/\/exams\/\d+$/);
        expect(page.url()).toContain(`/exams/${examId()}`);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(examName);

        await command(page, 'جلسة جديدة');
        const session = openDialog(page, 'جلسة جديدة');
        await choose(page, session, 'المادة', /./);
        await field(session, 'تاريخ الجلسة').fill('2026-12-03');
        await field(session, 'من الساعة').fill('09:00');
        await field(session, 'إلى الساعة').fill('11:00');
        await session.getByRole('button', { name: 'إضافة الجلسة' }).click();
        const sessionId = () => Number(dbValue("SELECT id FROM exams.exam_sessions WHERE exam_id = ? AND start_time = '09:00:00'", [examId()]));
        await expect.poll(sessionId).toBeGreaterThan(0);
        const status = () => Number(dbValue('SELECT status FROM exams.exam_sessions WHERE id = ?', [sessionId()]));
        expect(status()).toBe(1);
        // The subject is shown by name, not by its id.
        await expect(page.locator('tbody tr', { hasText: '09:00' })).not.toContainText(/^\s*\d+\s*$/);

        await page.locator('tbody tr', { hasText: '09:00' }).click();
        await command(page, 'بدء الجلسة');
        await expect.poll(status).toBe(2);
        await command(page, 'إغلاق الجلسة');
        await page.getByRole('dialog').getByRole('button', { name: 'إغلاق الجلسة' }).click();
        await expect.poll(status).toBe(3);
        await expect(page.getByRole('button', { name: 'بدء الجلسة', exact: true })).toBeDisabled();
    });

    test('the manager edits the exam, edits and cancels a session; cancelling the exam is refused', async ({ page }) => {
        const editedName = `${examName} — معدل`;
        const id = examId();
        await page.goto(`/exams/${id}`);

        await command(page, 'تعديل الامتحان');
        const edit = openDialog(page, 'تعديل الامتحان');
        await field(edit, 'اسم الامتحان').fill(editedName);
        await edit.getByRole('button', { name: 'حفظ', exact: true }).click();
        await expect.poll(() => dbValue('SELECT name FROM exams.exams WHERE id = ?', [id])).toBe(editedName);

        await command(page, 'جلسة جديدة');
        const add = openDialog(page, 'جلسة جديدة');
        await choose(page, add, 'المادة', /./);
        await field(add, 'تاريخ الجلسة').fill('2026-12-04');
        await field(add, 'من الساعة').fill('12:00');
        await field(add, 'إلى الساعة').fill('13:00');
        await add.getByRole('button', { name: 'إضافة الجلسة' }).click();
        const second = () => Number(dbValue("SELECT id FROM exams.exam_sessions WHERE exam_id = ? AND start_time = '12:00:00'", [id]));
        await expect.poll(second).toBeGreaterThan(0);

        await page.locator('tbody tr', { hasText: '12:00' }).click();
        await command(page, 'تعديل الجلسة');
        const sessionEdit = openDialog(page, /تعديل الجلسة/);
        await field(sessionEdit, 'إلى الساعة').fill('13:30');
        await sessionEdit.getByRole('button', { name: 'حفظ', exact: true }).click();
        await expect.poll(() => dbValue('SELECT end_time FROM exams.exam_sessions WHERE id = ?', [second()])).toBe('13:30:00');

        await page.locator('tbody tr', { hasText: '12:00' }).click();
        await command(page, 'إلغاء الجلسة');
        await page.getByRole('dialog').getByRole('button', { name: 'إلغاء الجلسة' }).click();
        await expect.poll(() => Number(dbValue('SELECT status FROM exams.exam_sessions WHERE id = ?', [second()]))).toBe(4);

        // The first session is completed: cancelling the exam is refused with the reason, nothing changes.
        await command(page, 'إلغاء الامتحان');
        await page.getByRole('dialog').getByRole('button', { name: 'إلغاء الامتحان' }).click();
        await expectNotice(page, 'لا يُلغى الامتحان لوجود جلسة مكتملة.');
        expect(Number(dbValue('SELECT status FROM exams.exams WHERE id = ?', [id]))).toBe(1);
    });

    test('a viewer gets no management command', async ({ pageAs }) => {
        const page = await pageAs('viewer');
        await page.goto(`/exams?academic_year_id=${f.academic_year_id}`);
        await editTab(page);
        await expect(page.getByRole('button', { name: 'عرض', exact: true }).first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'امتحان جديد' })).toHaveCount(0);

        await page.goto(`/exams/${examId()}`);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expect(page.getByRole('button', { name: /^(جلسة جديدة|بدء الجلسة|إغلاق الجلسة|تعديل الامتحان|إلغاء الامتحان)( — .*)?$/ })).toHaveCount(0);
    });

    test('@responsive the exam list and the exam page render without page-level horizontal scroll', async ({ page }, info) => {
        // The tablet / phone projects run only this test: use the fixture's exam (sis:e2e-seed-exams), not one made above.
        const fixtureExam = Number(dbValue('SELECT min(e.id) FROM exams.exams e JOIN exams.exam_sessions s ON s.exam_id = e.id WHERE s.id = ?', [
            (JSON.parse(fs.readFileSync(MANIFEST, 'utf8')) as { sessions: { open: number } }).sessions.open,
        ]));
        await noPageScroll(page, `/exams?academic_year_id=${f.academic_year_id}`, info);
        await noPageScroll(page, `/exams/${fixtureExam}`, info);
    });
});
