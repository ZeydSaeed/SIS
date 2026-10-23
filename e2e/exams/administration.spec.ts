/**
 * Exam administration from the web, through the real browser and server: the manager creates an exam, adds a session,
 * starts it and closes it (with confirmation); a viewer gets none of these controls.
 *
 * Needs an exam type, which only the exam fixture of `php artisan sis:e2e-seed-exams` provides (disposable sis_e2e_*
 * copy only); skipped on sis_e2e.
 */
import fs from 'node:fs';
import path from 'node:path';
import { dbValue } from '../support/db';
import { E2E_DB, RUN } from '../support/env';
import { expect, test } from '../support/test';

type Fixture = { database: string; academic_year_id: number };

const MANIFEST = path.join('storage', 'app', 'e2e', 'exam-fixture.json');

test.describe('exam administration', () => {
    test.describe.configure({ mode: 'serial' });
    test.skip(
        E2E_DB === 'sis_e2e',
        'exam types are only seeded on a disposable sis_e2e_* copy (php artisan sis:e2e-seed-exams)',
    );

    let f: Fixture;
    const examName = `اختبار امتحان من الواجهة ${RUN}`;
    test.beforeAll(() => {
        f = JSON.parse(fs.readFileSync(MANIFEST, 'utf8')) as Fixture;
        expect(f.database, `${MANIFEST} belongs to another database`).toBe(
            E2E_DB,
        );
    });

    test('the manager creates an exam, adds a session, starts and closes it', async ({
        page,
    }) => {
        await page.goto(`/exams?academic_year_id=${f.academic_year_id}`);
        await page.getByRole('button', { name: 'امتحان جديد' }).click();
        const create = page.locator('[data-window-id="exams.create"]');
        await create.locator('input[name="name"]').fill(examName);
        await create
            .locator('select[name="term_id"]')
            .selectOption({ index: 1 });
        await create
            .locator('select[name="exam_type_id"]')
            .selectOption({ index: 1 });
        await create.locator('input[name="start_date"]').fill('2026-12-01');
        await create.locator('input[name="end_date"]').fill('2026-12-10');
        await create.getByRole('button', { name: 'إنشاء الامتحان' }).click();

        await expect(page).toHaveURL(/\/exams\/\d+$/);
        const examId = Number(
            dbValue('SELECT id FROM exams.exams WHERE name = ?', [examName]),
        );
        expect(page.url()).toContain(`/exams/${examId}`);

        await page.getByRole('button', { name: 'جلسة جديدة' }).click();
        const session = page.locator('[data-window-id="exams.session.create"]');
        await session
            .locator('select[name="subject_id"]')
            .selectOption({ index: 1 });
        await session.locator('input[name="session_date"]').fill('2026-12-03');
        await session.locator('input[name="start_time"]').fill('09:00');
        await session.locator('input[name="end_time"]').fill('11:00');
        await session.getByRole('button', { name: 'إضافة الجلسة' }).click();

        await expect
            .poll(() =>
                Number(
                    dbValue(
                        'SELECT count(*) FROM exams.exam_sessions WHERE exam_id = ?',
                        [examId],
                    ),
                ),
            )
            .toBe(1);
        const sessionId = Number(
            dbValue('SELECT id FROM exams.exam_sessions WHERE exam_id = ?', [
                examId,
            ]),
        );
        const status = () =>
            Number(
                dbValue('SELECT status FROM exams.exam_sessions WHERE id = ?', [
                    sessionId,
                ]),
            );
        expect(status()).toBe(1);

        await page.getByRole('button', { name: 'بدء الجلسة' }).click();
        await expect.poll(status).toBe(2);

        await page.getByRole('button', { name: 'إغلاق الجلسة' }).click();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'إغلاق الجلسة' })
            .click();
        await expect.poll(status).toBe(3);
        await expect(
            page.getByRole('button', { name: 'بدء الجلسة' }),
        ).toHaveCount(0);
    });

    test('a viewer gets no administration controls', async ({ pageAs }) => {
        const page = await pageAs('viewer');
        await page.goto(`/exams?academic_year_id=${f.academic_year_id}`);
        await expect(
            page.getByRole('button', { name: 'امتحان جديد' }),
        ).toHaveCount(0);

        const examId = Number(
            dbValue('SELECT id FROM exams.exams WHERE name = ?', [examName]),
        );
        await page.goto(`/exams/${examId}`);
        await expect(
            page.getByRole('button', { name: 'جلسة جديدة' }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('button', {
                name: /بدء الجلسة|إغلاق الجلسة|إجلاس طلاب/,
            }),
        ).toHaveCount(0);
    });
});
