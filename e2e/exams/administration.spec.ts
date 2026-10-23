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
import { command, editTab } from '../support/examis';
import { choose, expectNotice, field, openDialog } from '../support/ui';

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
        await command(page, 'امتحان جديد');
        const create = openDialog(page, 'امتحان جديد');
        await field(create, 'اسم الامتحان').fill(examName);
        await choose(page, create, 'الفصل', /./);
        await choose(page, create, 'نوع الامتحان', /./);
        await field(create, 'تاريخ البداية').fill('2026-12-01');
        await field(create, 'تاريخ النهاية').fill('2026-12-10');
        await create.getByRole('button', { name: 'إنشاء الامتحان' }).click();

        await expect(page).toHaveURL(/\/exams\/\d+$/);
        const examId = Number(
            dbValue('SELECT id FROM exams.exams WHERE name = ?', [examName]),
        );
        expect(page.url()).toContain(`/exams/${examId}`);

        await command(page, 'جلسة جديدة');
        const session = openDialog(page, 'جلسة جديدة');
        await choose(page, session, 'المادة', /./);
        await field(session, 'تاريخ الجلسة').fill('2026-12-03');
        await field(session, 'من الساعة').fill('09:00');
        await field(session, 'إلى الساعة').fill('11:00');
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

        await page.locator('tbody tr', { hasText: '09:00' }).click();
        await command(page, 'بدء الجلسة');
        await expect.poll(status).toBe(2);

        await command(page, 'إغلاق الجلسة');
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'إغلاق الجلسة' })
            .click();
        await expect.poll(status).toBe(3);
        await expect(
            page.getByRole('button', { name: 'بدء الجلسة', exact: true }),
        ).toBeDisabled();
    });

    test('the manager edits the exam, edits and cancels a session, then cancels the exam', async ({
        page,
    }) => {
        const editedName = `${examName} — معدل`;
        const examId = Number(
            dbValue('SELECT id FROM exams.exams WHERE name = ?', [examName]),
        );
        await page.goto(`/exams/${examId}`);

        // The exam of the first test is Draft: its name can change.
        await command(page, 'تعديل الامتحان');
        const edit = openDialog(page, 'تعديل الامتحان');
        await field(edit, 'اسم الامتحان').fill(editedName);
        await edit.getByRole('button', { name: 'حفظ', exact: true }).click();
        await expect
            .poll(() =>
                dbValue('SELECT name FROM exams.exams WHERE id = ?', [examId]),
            )
            .toBe(editedName);

        // A second, scheduled session: edit its end time, then cancel it.
        await command(page, 'جلسة جديدة');
        const add = openDialog(page, 'جلسة جديدة');
        await choose(page, add, 'المادة', /./);
        await field(add, 'تاريخ الجلسة').fill('2026-12-04');
        await field(add, 'من الساعة').fill('12:00');
        await field(add, 'إلى الساعة').fill('13:00');
        await add.getByRole('button', { name: 'إضافة الجلسة' }).click();
        const second = () =>
            Number(
                dbValue(
                    "SELECT id FROM exams.exam_sessions WHERE exam_id = ? AND start_time = '12:00:00'",
                    [examId],
                ),
            );
        await expect.poll(second).toBeGreaterThan(0);

        await page.locator('tbody tr', { hasText: '12:00' }).click();
        await command(page, 'تعديل الجلسة');
        const sessionEdit = openDialog(page, /تعديل الجلسة/);
        await field(sessionEdit, 'إلى الساعة').fill('13:30');
        await sessionEdit
            .getByRole('button', { name: 'حفظ', exact: true })
            .click();
        await expect
            .poll(() =>
                dbValue(
                    'SELECT end_time FROM exams.exam_sessions WHERE id = ?',
                    [second()],
                ),
            )
            .toBe('13:30:00');

        await page.locator('tbody tr', { hasText: '12:00' }).click();
        await command(page, 'إلغاء الجلسة');
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'إلغاء الجلسة' })
            .click();
        await expect
            .poll(() =>
                Number(
                    dbValue(
                        'SELECT status FROM exams.exam_sessions WHERE id = ?',
                        [second()],
                    ),
                ),
            )
            .toBe(4);

        // The first session was completed: cancelling the exam is refused with the reason, nothing changes.
        await command(page, 'إلغاء الامتحان');
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'إلغاء الامتحان' })
            .click();
        await expectNotice(page, 'لا يُلغى الامتحان لوجود جلسة مكتملة.');
        expect(
            Number(
                dbValue('SELECT status FROM exams.exams WHERE id = ?', [
                    examId,
                ]),
            ),
        ).toBe(1);
    });

    test('a viewer gets no administration controls', async ({ pageAs }) => {
        const page = await pageAs('viewer');
        await page.goto(`/exams?academic_year_id=${f.academic_year_id}`);
        await editTab(page);
        await expect(
            page.getByRole('button', { name: 'عرض', exact: true }).first(),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'امتحان جديد' }),
        ).toHaveCount(0);

        const examId = Number(
            dbValue('SELECT id FROM exams.exams WHERE name LIKE ?', [
                `${examName}%`,
            ]),
        );
        await page.goto(`/exams/${examId}`);
        await editTab(page);
        await expect(
            page.getByRole('button', { name: 'كشف المرشحين' }).first(),
        ).toBeVisible();
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
