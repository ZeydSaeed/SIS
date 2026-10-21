/**
 * «المعلمون» through the page: register a teacher with every field of the sheet (the database holds exactly what
 * was typed), find them, edit them, refuse what the rules refuse (with the message shown and nothing written), and
 * deactivate behind a confirmation.
 */
import { dbQuery, dbValue } from '../support/db';
import { RUN } from '../support/env';
import { expect, test } from '../support/test';
import { choose, closeNotice, expectNotice, field, notice, openDialog, ribbon } from '../support/ui';

test.describe.configure({ mode: 'serial' });

const code = `T-UI-${RUN}`;
const nationalId = `1992${String(parseInt(RUN, 36) % 1e8).padStart(8, '0')}`;

type TeacherRow = { full_name: string; national_id: string | null; specialization_field: string | null; hire_date: string | null; status: number; employment_type: number | null; abbreviation: string | null };

const teacherRow = (): TeacherRow | undefined =>
    dbQuery<TeacherRow>(
        `SELECT t.full_name, t.national_id, t.specialization_field, t.hire_date::text, t.status, ts.employment_type, t.abbreviation
           FROM teachers.teachers t LEFT JOIN teachers.teacher_schools ts ON ts.teacher_id = t.id AND ts.school_id = 1
          WHERE t.employee_code = ?`,
        [code],
    )[0];

async function openAddSheet(page: import('@playwright/test').Page) {
    await page.goto('/teachers');
    await ribbon(page, 'اضافة', 'إضافة معلم جديد');
    const sheet = openDialog(page, 'إضافة معلم جديد');
    await expect(sheet).toBeVisible();

    return sheet;
}

async function fillRequired(sheet: import('@playwright/test').Locator, values: { first: string; last: string; code: string }) {
    await field(sheet, 'الاسم الأول').fill(values.first);
    await field(sheet, 'اللقب').fill(values.last);
    await field(sheet, 'الرقم الوظيفي').fill(values.code);
}

test('register a teacher with every field of the sheet', async ({ page }) => {
    const sheet = await openAddSheet(page);
    await expect(sheet.getByRole('button', { name: 'إضافة', exact: true }), 'save stays disabled until the required fields are filled').toBeDisabled();

    await fillRequired(sheet, { first: 'سلمان', last: 'الاختباري', code });
    await field(sheet, 'اسم الأب').fill('جواد');
    await field(sheet, 'اسم الجد').fill('كاظم');
    await field(sheet, 'الرقم الوطني').fill(nationalId);
    await field(sheet, 'التخصص (تخصص الشهادة)').fill('هندسة حاسبات');
    await choose(page, sheet, 'اللقب العلمي', /^مهندس/);
    await field(sheet, 'الاختصار (في الجدول)').fill('سل');
    await choose(page, sheet, 'نوع التعيين', /^ملاك/);
    await field(sheet, 'تاريخ التعيين').fill('2015-09-01');
    await sheet.getByRole('button', { name: 'إضافة', exact: true }).click();

    await expectNotice(page, 'تمت إضافة المعلم');
    await expect(sheet).toBeHidden();
    await closeNotice(page, 'تمت إضافة المعلم');
    expect(teacherRow()).toEqual({
        full_name: 'سلمان جواد كاظم الاختباري',
        national_id: nationalId,
        specialization_field: 'هندسة حاسبات',
        hire_date: '2015-09-01',
        status: 1,
        employment_type: 1,
        abbreviation: 'سل',
    });

    // The new teacher is found by the page's search.
    await page.getByRole('searchbox', { name: 'بحث في المعلمين' }).fill(code);
    await expect(page.getByRole('row').filter({ hasText: code })).toHaveCount(1);
    await expect(page.getByRole('row').filter({ hasText: 'سلمان جواد كاظم الاختباري' })).toBeVisible();
});

const refusals: [string, (sheet: import('@playwright/test').Locator) => Promise<void>, RegExp][] = [
    ['an employee code already in use', (s) => fillRequired(s, { first: 'مكرر', last: 'الرمز', code: 'T-S001' }), /الرمز الوظيفي مستخدم لمعلم آخر/],
    ['the same code in lower case', (s) => fillRequired(s, { first: 'مكرر', last: 'الرمز', code: 't-s001' }), /الرمز الوظيفي مستخدم لمعلم آخر/],
    ['a national id already in use', async (s) => {
        await fillRequired(s, { first: 'مكرر', last: 'الوطني', code: `${code}-N` });
        await field(s, 'الرقم الوطني').fill(nationalId);
    }, /الرقم الوطني مسجّل لمعلم آخر/],
    ['a hire date in the future', async (s) => {
        await fillRequired(s, { first: 'تاريخ', last: 'مستقبلي', code: `${code}-F` });
        await field(s, 'تاريخ التعيين').fill('2099-01-01');
    }, /تاريخ التعيين لا يمكن أن يكون في المستقبل/],
    ['a first name longer than 100 characters', (s) => fillRequired(s, { first: 'ا'.repeat(101), last: 'طويل', code: `${code}-L` }), /100/],
];
for (const [label, fill, message] of refusals) {
    test(`refused: ${label} — message shown, input kept, nothing written`, async ({ page }) => {
        const before = Number(dbValue('SELECT count(*) FROM teachers.teachers'));
        const sheet = await openAddSheet(page);
        await fill(sheet);
        const typed = await field(sheet, 'الاسم الأول').inputValue();
        await sheet.getByRole('button', { name: 'إضافة', exact: true }).click();

        await expect(notice(page, message)).toBeVisible();
        await expect(sheet, 'the sheet stays open').toBeVisible();
        await expect(field(sheet, 'الاسم الأول')).toHaveValue(typed);
        expect(Number(dbValue('SELECT count(*) FROM teachers.teachers'))).toBe(before);
    });
}

test('edit the teacher', async ({ page }) => {
    await page.goto('/teachers');
    await page.getByRole('searchbox', { name: 'بحث في المعلمين' }).fill(code);
    await page.getByRole('checkbox', { name: /تحديد المعلم: سلمان/ }).check();
    await ribbon(page, 'تحرير', /^تعديل/);
    const sheet = openDialog(page);
    await field(sheet, 'اسم الجد').fill('عبد الكاظم');
    await field(sheet, 'التخصص (تخصص الشهادة)').fill('هندسة شبكات');
    await sheet.getByRole('button', { name: /^حفظ/ }).click();

    await expectNotice(page, /تم/);
    await expect(sheet).toBeHidden();
    const row = teacherRow();
    expect(row?.full_name).toBe('سلمان جواد عبد الكاظم الاختباري');
    expect(row?.specialization_field).toBe('هندسة شبكات');
    expect(row?.national_id).toBe(nationalId);
});

test('deactivate the teacher only after confirming', async ({ page }) => {
    await page.goto('/teachers');
    await page.getByRole('searchbox', { name: 'بحث في المعلمين' }).fill(code);
    await page.getByRole('checkbox', { name: /تحديد المعلم: سلمان/ }).check();
    await ribbon(page, 'تحرير', /إيقاف/);
    const confirm = openDialog(page, 'إيقاف المعلم');
    await expect(confirm).toContainText('يبقى سجله محفوظاً');
    expect(teacherRow()?.status, 'nothing changes before the confirmation').toBe(1);
    await confirm.getByRole('button', { name: 'إيقاف', exact: true }).click();

    await expect.poll(() => teacherRow()?.status).toBe(2);
});
