/**
 * «التسجيل» (enrollment): a waiting student is placed through the page (year → branch → department → class →
 * section) and the enrollment and the student's placement agree; a full section, a second enrollment in the same
 * year, a date outside the year and a section of another class are refused with their message and nothing
 * written; withdrawing ends the enrollment without deleting it.
 */
import { dbQuery, dbValue } from '../support/db';
import { attempt } from '../support/http';
import { expect, test } from '../support/test';
import { choose, closeNotice, expectNotice, field, notice } from '../support/ui';

test.describe.configure({ mode: 'serial' });

const YEAR = () => dbValue<number>('SELECT id FROM academic.academic_years WHERE is_current');
/** An active student of the school with no enrollment this year, admitted for الصناعي › كهرباء. */
const waiting = (skip = 0) => dbValue<number>(
    `SELECT s.id FROM students.students s
      WHERE s.school_id = 1 AND s.status = 1
        AND NOT EXISTS (SELECT 1 FROM enrollment.enrollments e WHERE e.student_id = s.id AND e.status = 1)
      ORDER BY s.id OFFSET ? LIMIT 1`, [skip]);
const section = (cls: string, sec: string) => dbQuery<{ id: number; class_id: number }>(
    'SELECT s.id, s.class_id FROM enrollment.sections s JOIN enrollment.classes c ON c.id = s.class_id WHERE c.code = ? AND s.code = ? AND c.academic_year_id = (SELECT id FROM academic.academic_years WHERE is_current)',
    [cls, sec])[0];
const placement = () => dbQuery<{ id: number; branch: number; department: number }>(
    "SELECT d.id AS department, d.branch_id AS branch FROM organization.departments d JOIN organization.branches b ON b.id = d.branch_id WHERE b.school_id = 1 AND b.name = 'الصناعي' AND d.name = 'كهرباء' AND d.status = 1 ORDER BY d.id LIMIT 1")[0];

let studentId = 0;

test('place a waiting student through the page', async ({ page }) => {
    studentId = waiting();
    expect(studentId, 'the dataset has a student waiting for a seat').toBeGreaterThan(0);
    await page.goto('/enrollments/create');
    await field(page, 'معرّف الطالب').fill(String(studentId));
    await field(page, 'ساري من').fill('2026-10-01');
    await choose(page, page.locator('main'), 'الفرع', /^الصناعي/);
    await choose(page, page.locator('main'), 'الاختصاص', /^كهرباء/);
    await choose(page, page.locator('main'), 'الصف', /^الأول/);
    await choose(page, page.locator('main'), 'الشعبة', /^A/);
    await page.getByRole('button', { name: 'إنشاء التسجيل' }).click();

    await expectNotice(page, /تم/);
    await closeNotice(page, /تم/);
    const row = dbQuery<Record<string, unknown>>(
        `SELECT e.status, e.effective_from::text, c.code AS class, s.code AS section, d.name AS department, b.name AS branch,
                st.department_id = e.department_id AS student_follows
           FROM enrollment.enrollments e JOIN enrollment.sections s ON s.id = e.section_id JOIN enrollment.classes c ON c.id = e.class_id
           JOIN organization.departments d ON d.id = e.department_id JOIN organization.branches b ON b.id = e.branch_id
           JOIN students.students st ON st.id = e.student_id
          WHERE e.student_id = ? AND e.status = 1`, [studentId])[0];
    expect(row).toEqual({ status: 1, effective_from: '2026-10-01', class: 'CLS-1', section: 'SEC-A', department: 'كهرباء', branch: 'الصناعي', student_follows: true });
});

test('a typed student id is shown by name before saving; an id of no student of the school says so', async ({ page }) => {
    const id = waiting();
    const name = dbValue<string>('SELECT full_name FROM students.students WHERE id = ?', [id]);
    await page.goto('/enrollments/create');
    await field(page, 'معرّف الطالب').fill(String(id));
    await expect(page.getByRole('heading', { name })).toBeVisible();

    // Wrong id corrected in place (the field stays editable after it resolved).
    await field(page, 'معرّف الطالب').fill('99999999');
    await expect(page.getByText('لا يوجد طالب بهذا المعرّف في هذه المدرسة')).toBeVisible();
    await field(page, 'معرّف الطالب').fill(String(id));
    await expect(page.getByRole('heading', { name })).toBeVisible();
});

test("the lookup never names a student of another school", async ({ pageAs }) => {
    const page = await pageAs('school2');
    const id = waiting();
    const name = dbValue<string>('SELECT full_name FROM students.students WHERE id = ?', [id]);
    await page.goto('/enrollments/create');
    await field(page, 'معرّف الطالب').fill(String(id));
    await expect(page.getByText('لا يوجد طالب بهذا المعرّف في هذه المدرسة')).toBeVisible();
    await expect(page.locator('body')).not.toContainText(name);
});

test('a full section is refused through the page with its message', async ({ page, watch }) => {
    watch.allow(/\/enrollments$/, 422);
    const full = section('CLS-3', 'SEC-C');
    expect(Number(dbValue('SELECT count(*) FROM enrollment.enrollments WHERE section_id = ? AND status = 1', [full.id]))).toBe(
        Number(dbValue('SELECT capacity FROM enrollment.sections WHERE id = ?', [full.id])),
    );
    const before = Number(dbValue('SELECT count(*) FROM enrollment.enrollments'));
    await page.goto('/enrollments/create');
    await field(page, 'معرّف الطالب').fill(String(waiting()));
    await field(page, 'ساري من').fill('2026-10-01');
    await choose(page, page.locator('main'), 'الفرع', /^الصناعي/);
    await choose(page, page.locator('main'), 'الاختصاص', /^كهرباء/);
    await choose(page, page.locator('main'), 'الصف', /^الثالث/);
    await choose(page, page.locator('main'), 'الشعبة', /^C/);
    await page.getByRole('button', { name: 'إنشاء التسجيل' }).click();

    await expect(notice(page, 'وصل إلى السعة القصوى')).toBeVisible();
    expect(Number(dbValue('SELECT count(*) FROM enrollment.enrollments'))).toBe(before);
});

test('the enrollment rules at the server', async ({ page }) => {
    // Long by design (6 refusals, each followed by its page; ≈2 s per request + page + database check on one PHP server): every step still asserts.
    test.setTimeout(180_000);
    await page.goto('/enrollments');
    const p = placement();
    const a = section('CLS-1', 'SEC-B');
    const third = section('CLS-3', 'SEC-A');
    const base = (student: number) => ({ student_id: student, academic_year_id: YEAR(), class_id: a.class_id, section_id: a.id, effective_from: '2026-10-02', branch_id: p.branch, department_id: p.department });
    const cases: [string, Record<string, unknown>, RegExp][] = [
        ['a second enrollment in the same year', base(studentId), /already_enrolled|مسجّل مسبقاً/],
        ['a start date outside the year', { ...base(waiting(1)), effective_from: '1999-01-01' }, /date_outside_year|خارج السنة/],
        ['a section of another class', { ...base(waiting(1)), section_id: third.id }, /invalid_placement|section_not_found|class/],
        ['an unknown student', { ...base(99_999_999) }, /student|not_found|ineligible/],
        ['an end date set at creation', { ...base(waiting(1)), effective_to: '2027-06-30' }, /effective_to/],
        ['a school smuggled in', { ...base(waiting(1)), school_id: 2 }, /school_id/],
    ];
    for (const [label, data, expected] of cases) {
        const before = Number(dbValue('SELECT count(*) FROM enrollment.enrollments'));
        const result = await attempt(page, 'POST', '/enrollments', data);
        expect(result.status, `${label}: no server error`).toBeLessThan(500);
        expect(result.ok, `${label} must be refused`).toBe(false);
        expect(JSON.stringify(result.errors), label).toMatch(expected);
        expect(Number(dbValue('SELECT count(*) FROM enrollment.enrollments')), `${label}: nothing written`).toBe(before);
    }
});

test('the edit page uses names, keeps what was not changed and confirms the save', async ({ page }) => {
    const row = dbQuery<{ id: number; department_id: number; section_id: number; class_id: number; full_name: string }>(
        `SELECT e.id, e.department_id, e.section_id, e.class_id, s.full_name FROM enrollment.enrollments e JOIN students.students s ON s.id = e.student_id
          WHERE e.status = 1 AND e.department_id IS NOT NULL AND e.school_id = 1 ORDER BY e.id LIMIT 1`)[0];
    await page.goto(`/enrollments/${row.id}/edit`);
    await expect(page.getByText(row.full_name)).toBeVisible();
    await expect(page.locator('input[type=number]'), 'no raw-id inputs').toHaveCount(0);

    const student = dbValue<number>('SELECT student_id FROM enrollment.enrollments WHERE id = ?', [row.id]);
    const history = () => Number(dbValue('SELECT count(*) FROM enrollment.enrollments WHERE student_id = ?', [student]));
    const rows = history();

    // Saved untouched: nothing moves, no new row, and the save is confirmed (it used to say nothing).
    await page.getByRole('button', { name: /تحديث|حفظ/ }).last().click();
    await expect(notice(page, 'تم تحديث التوزيع بنجاح')).toBeVisible();
    expect(dbQuery('SELECT section_id, department_id, status FROM enrollment.enrollments WHERE id = ?', [row.id])[0]).toEqual({ section_id: row.section_id, department_id: row.department_id, status: 1 });
    expect(history()).toBe(rows);
    await closeNotice(page, 'تم تحديث التوزيع بنجاح');

    // Another section of the same class, chosen by name: the old placement is closed and kept (status 5 + end date),
    // a new active enrollment holds the new section — history is never overwritten.
    const other = dbQuery<{ id: number }>('SELECT id FROM enrollment.sections WHERE class_id = ? AND id <> ? AND (capacity IS NULL OR capacity > 2) ORDER BY id LIMIT 1', [row.class_id, row.section_id])[0];
    await page.locator('select[name=section_id]').selectOption(String(other.id));
    await page.getByRole('button', { name: /تحديث|حفظ/ }).last().click();
    await expect(notice(page, 'تم تحديث التوزيع بنجاح')).toBeVisible();
    expect(dbQuery('SELECT section_id, status, effective_to IS NOT NULL AS ended FROM enrollment.enrollments WHERE id = ?', [row.id])[0]).toEqual({ section_id: row.section_id, status: 5, ended: true });
    expect(dbQuery('SELECT section_id, department_id FROM enrollment.enrollments WHERE student_id = ? AND status = 1', [student])).toEqual([{ section_id: other.id, department_id: row.department_id }]);
});

test('withdrawing ends the enrollment and keeps it', async ({ page }) => {
    const id = dbValue<number>('SELECT id FROM enrollment.enrollments WHERE student_id = ? AND status = 1', [studentId]);
    // Unified «حالة الطالب» code 4 = منسحب.
    const result = await attempt(page, 'POST', '/enrollments/bulk-status', { enrollment_ids: [id], status: 4 });
    expect(result.ok, JSON.stringify(result.errors)).toBe(true);
    const row = dbQuery<{ status: number; effective_to: string | null }>('SELECT status, effective_to::text FROM enrollment.enrollments WHERE id = ?', [id])[0];
    expect(row.status, 'no longer active').not.toBe(1);
    test.info().annotations.push({ type: 'withdrawn enrollment', description: JSON.stringify(row) });
    expect(Number(dbValue('SELECT count(*) FROM enrollment.enrollments WHERE id = ?', [id])), 'the record is kept').toBe(1);
});
