/**
 * «الطلاب»: a new student is created through admission («إضافة طالب جديد» → طلب قبول → طالب), with every field of
 * the form; the student and its application hold exactly what was typed. The form's rules are probed at and past
 * every boundary (the server decides, not the browser), a duplicate national id is refused, and «حذف» only
 * withdraws the student — the record stays.
 */
import type { Locator, Page } from '@playwright/test';
import { dbQuery, dbValue } from '../support/db';
import { RUN } from '../support/env';
import { attempt } from '../support/http';
import { expect, test } from '../support/test';
import { choose, closeNotice, expectNotice, field, notice, openDialog, ribbon } from '../support/ui';

test.describe.configure({ mode: 'serial' });

const surname = `اختبار${RUN}`;
const nationalId = `2${String(parseInt(RUN, 36) % 1e11).padStart(11, '0')}`;

type StudentRow = Record<string, unknown>;
const student = (): StudentRow | undefined => dbQuery<StudentRow>(
    `SELECT s.id, s.full_name, s.national_id, s.birth_date::text, s.gender, s.status, s.birth_place,
            a.status AS application_status, a.branch_name, a.department_name, a.intended_grade_name, a.previous_gpa::float AS previous_gpa,
            a.graduation_year, a.student_mobile, a.guardian_mobile, a.governorate, a.neighborhood, a.father_occupation, a.mother_occupation
       FROM students.students s LEFT JOIN admission.applications a ON a.student_id = s.id
      WHERE s.last_name = ?`, [surname])[0];

async function openStudentForm(page: Page): Promise<Locator> {
    await page.goto('/students');
    await ribbon(page, 'اضافة', 'طالب');
    const form = openDialog(page, 'إضافة طالب جديد');
    await expect(form).toBeVisible();

    return form;
}

test('create a student through admission with every field of the form', async ({ page }) => {
    const form = await openStudentForm(page);
    await choose(page, form, 'الفرع', /^الصناعي/);
    await choose(page, form, 'الاختصاص', /^كهرباء/);
    await choose(page, form, 'الصف', /^الأول/);
    await field(form, 'الاسم').fill('مصطفى');
    await field(form, 'اسم الأب').fill('حيدر');
    await field(form, 'اسم الجد').fill('عباس');
    await field(form, 'اسم أب الجد').fill('كريم');
    await field(form, 'اللقب').fill(surname);
    await field(form, 'التولد').fill('2010-03-15');
    await field(form, 'محل الولادة').fill('بغداد');
    await field(form, 'رقم البطاقة الوطنية').fill(nationalId);
    await form.getByRole('radio', { name: 'ذكر' }).check();
    await field(form, 'اسم الأم').fill('زهراء');
    await field(form, 'اسم أب الأم').fill('سلمان');
    await field(form, 'اسم جد الأم').fill('هادي');
    await field(form, 'وظيفة الأب').fill('موظف');
    await field(form, 'وظيفة الأم').fill('معلمة');
    await field(form, 'المحافظة').fill('بغداد');
    await field(form, 'الحي').fill('الكرادة');
    await field(form, 'رقم موبايل الطالب').fill('07701234567');
    await field(form, 'رقم موبايل ولي أمر الطالب').fill('07809876543');
    await field(form, 'اسم المتوسطة المتخرج منها').fill('متوسطة الرافدين');
    await field(form, 'سنة التخرج').fill('2025');
    await field(form, 'المعدل').fill('78.5');
    await form.getByRole('button', { name: 'إنشاء طالب جديد' }).click();

    await expect(form).toBeHidden({ timeout: 20_000 });
    await expectNotice(page, /تم/);
    await closeNotice(page, /تم/);
    expect(student()).toMatchObject({
        full_name: `مصطفى حيدر عباس كريم ${surname}`,
        national_id: nationalId,
        birth_date: '2010-03-15',
        gender: 1,
        birth_place: 'بغداد',
        branch_name: 'الصناعي',
        department_name: 'كهرباء',
        previous_gpa: 78.5,
        graduation_year: 2025,
        student_mobile: '07701234567',
        guardian_mobile: '07809876543',
        governorate: 'بغداد',
        neighborhood: 'الكرادة',
        father_occupation: 'موظف',
        mother_occupation: 'معلمة',
    });

    // Saving lands on «القبول» (the new application is followed up there); the student is in the students list too.
    await expect(page).toHaveURL(/\/admission/);
    await page.goto('/students');
    await page.getByRole('searchbox', { name: 'بحث الطلاب' }).fill(surname);
    await expect(page.getByRole('row').filter({ hasText: surname })).toHaveCount(1);
});

test('incomplete data stops at the first missing field, the input kept and nothing sent', async ({ page }) => {
    const before = Number(dbValue('SELECT count(*) FROM students.students'));
    const form = await openStudentForm(page);
    await field(form, 'الاسم').fill('ناقص');
    const requests: string[] = [];
    page.on('request', (r) => r.method() !== 'GET' && requests.push(r.url()));
    await form.getByRole('button', { name: 'إنشاء طالب جديد' }).click();

    // The browser's own required-field check (its bubble is in the browser's language); the server rules for
    // missing fields are covered by the boundary test below.
    const father = field(form, 'اسم الأب');
    await expect(father).toBeFocused();
    expect(await father.evaluate((el: HTMLInputElement) => el.validity.valueMissing)).toBe(true);
    await expect(form, 'the form stays open').toBeVisible();
    await expect(field(form, 'الاسم')).toHaveValue('ناقص');
    expect(requests.filter((u) => u.includes('register-student')), 'nothing sent').toEqual([]);
    expect(Number(dbValue('SELECT count(*) FROM students.students'))).toBe(before);
});

test('every rule of the student form at and past its boundary (server side)', async ({ page }) => {
    await page.goto('/students');
    // The open period (status 1; 2 = archived).
    const period = dbValue<number>('SELECT id FROM admission.application_periods WHERE status = 1 ORDER BY id DESC LIMIT 1');
    let n = 0;
    const valid = () => ({
        application_period_id: period, target_school_id: 1, request_kind: 1, intended_grade_name: 'الأول',
        branch_name: 'الصناعي', department_name: 'كهرباء', gender: 2, birth_date: '2010-01-01', birth_place: 'البصرة',
        first_name: 'حدود', father_name: 'أ', grandfather_name: 'ب', great_grandfather_name: 'ج', last_name: `${surname}ح${++n}`,
        // Optional on the form, required by sis:audit-data-quality — see the open questions in the coverage register.
        national_id: `3${String(parseInt(RUN, 36) % 1e9).padStart(9, '0')}${String(n).padStart(2, '0')}`,
        mother_name: 'د', maternal_father_name: 'هـ', maternal_grandfather_name: 'و',
    });
    const cases: [string, Record<string, unknown>, boolean][] = [
        ['GPA 0', { previous_gpa: 0 }, true], ['GPA 100', { previous_gpa: 100 }, true], ['GPA 100.5', { previous_gpa: 100.5 }, false], ['GPA -1', { previous_gpa: -1 }, false],
        ['birth date today', { birth_date: new Date().toISOString().slice(0, 10) }, false], ['birth date 1949-12-31', { birth_date: '1949-12-31' }, false],
        ['birth date «أمس»', { birth_date: 'أمس' }, false],
        ['graduation year 1949', { graduation_year: 1949 }, false], ['graduation year 2101', { graduation_year: 2101 }, false],
        ['gender 3', { gender: 3 }, false], ['request kind 9', { request_kind: 9 }, false],
        ['first name of 100 characters', { first_name: 'م'.repeat(100) }, true], ['first name of 101 characters', { first_name: 'م'.repeat(101) }, false],
        ['missing mother name', { mother_name: '' }, false], ['national id of 21 characters', { national_id: '1'.repeat(21) }, false],
        ['administrative unit 4', { administrative_unit: 4 }, false], ['previous study track 7', { previous_study_track: 7 }, false],
        ['notes of 5001 characters', { notes: 'م'.repeat(5001) }, false],
        ['duplicate national id', { national_id: nationalId }, false],
    ];
    const outcomes: string[] = [];
    for (const [label, change, accepted] of cases) {
        const before = Number(dbValue('SELECT count(*) FROM students.students'));
        const result = await attempt(page, 'POST', '/admission/applications/register-student', { ...valid(), ...change });
        expect(result.status, `${label}: no server error`).toBeLessThan(500);
        outcomes.push(`${label}: ${result.ok ? 'accepted' : 'refused ' + Object.values(result.errors).join(', ')}`);
        expect(result.ok, `${label} → ${JSON.stringify(result.errors)}`).toBe(accepted);
        expect(Number(dbValue('SELECT count(*) FROM students.students')) - before, `${label}: students written`).toBe(accepted ? 1 : 0);
    }
    test.info().annotations.push({ type: 'boundaries', description: outcomes.join(' · ') });

    // Sent by names only, the placement still resolves consistently: the branch is the department's own.
    expect(Number(dbValue(
        `SELECT count(*) FROM admission.applications a JOIN organization.departments d ON d.id = a.department_id
          WHERE a.last_name LIKE ? AND a.branch_id IS DISTINCT FROM d.branch_id`, [`${surname}ح%`],
    ))).toBe(0);
});

test('«حذف» withdraws the student — the record stays', async ({ page }) => {
    const id = student()?.id;
    await page.goto('/students');
    await page.getByRole('searchbox', { name: 'بحث الطلاب' }).fill(surname);
    await page.getByRole('checkbox', { name: `اختيار الطالب: مصطفى حيدر عباس كريم ${surname}` }).check();
    await ribbon(page, 'تحرير', 'حذف');
    const confirm = openDialog(page, /حذف/);
    await expect(confirm).toBeVisible();
    await confirm.getByRole('button', { name: 'حذف', exact: true }).click();

    await expect.poll(() => dbValue<number>('SELECT status FROM students.students WHERE id = ?', [id])).toBe(4); // withdrawn
    expect(Number(dbValue('SELECT count(*) FROM students.students WHERE id = ?', [id])), 'the record is kept').toBe(1);
    await expect(notice(page, /خطأ|تعذر/)).toHaveCount(0);
});
