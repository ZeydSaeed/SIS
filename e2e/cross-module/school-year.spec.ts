/**
 * Workflow H — one student through the modules, each consuming what the previous one produced:
 * admission (registered by names) → enrollment (section B of the first class) → curriculum subjects on the
 * enrollment → the student's timetable (the section's lessons) → today's attendance sheet of one of those lessons
 * lists the student and records them → the attendance report counts them.
 */
import { dbQuery, dbValue } from '../support/db';
import { RUN } from '../support/env';
import { attempt, send } from '../support/http';
import { runQueue } from '../support/queue';
import { expect, inertiaPage, test } from '../support/test';

test.describe.configure({ mode: 'serial' });

// Records made by tests carry «اختبار» (the setup counts the seeded batch without them).
const surname = `اختبارمسار${RUN}`;
const YEAR = () => dbValue<number>('SELECT id FROM academic.academic_years WHERE is_current');
let studentId = 0;
let enrollmentId = 0;

test('admission registers the student', async ({ page }) => {
    await page.goto('/students');
    const result = await attempt(page, 'POST', '/admission/applications/register-student', {
        application_period_id: dbValue<number>('SELECT id FROM admission.application_periods WHERE status = 1 ORDER BY id DESC LIMIT 1'),
        target_school_id: 1, request_kind: 1, intended_grade_name: 'الأول', branch_name: 'الصناعي', department_name: 'كهرباء',
        first_name: 'يوسف', father_name: 'كريم', grandfather_name: 'جاسم', great_grandfather_name: 'علي', last_name: surname,
        mother_name: 'سعاد', maternal_father_name: 'حميد', maternal_grandfather_name: 'كاظم', gender: 1, birth_date: '2010-05-05', birth_place: 'النجف',
        national_id: `4${String(parseInt(RUN, 36) % 1e11).padStart(11, '0')}`,
    });
    expect(result.ok, JSON.stringify(result.errors)).toBe(true);
    studentId = dbValue<number>('SELECT id FROM students.students WHERE last_name = ?', [surname]);
    expect(studentId).toBeGreaterThan(0);
    expect(dbValue<number>('SELECT count(*) FROM admission.applications WHERE student_id = ?', [studentId]), 'linked to its application').toBe(1);
});

test('enrollment places the student and the curriculum gives the subjects', async ({ page }) => {
    await page.goto('/enrollments');
    const section = dbQuery<{ id: number; class_id: number }>(
        "SELECT s.id, s.class_id FROM enrollment.sections s JOIN enrollment.classes c ON c.id = s.class_id WHERE c.code = 'CLS-1' AND s.code = 'SEC-B' AND c.academic_year_id = (SELECT id FROM academic.academic_years WHERE is_current)")[0];
    const department = dbQuery<{ id: number; branch_id: number }>("SELECT d.id, d.branch_id FROM organization.departments d JOIN organization.branches b ON b.id = d.branch_id WHERE b.school_id = 1 AND d.name = 'كهرباء' AND d.status = 1 ORDER BY d.id LIMIT 1")[0];
    const result = await attempt(page, 'POST', '/enrollments', {
        student_id: studentId, academic_year_id: YEAR(), class_id: section.class_id, section_id: section.id, effective_from: '2026-10-01',
        branch_id: department.branch_id, department_id: department.id,
    });
    expect(result.ok, JSON.stringify(result.errors)).toBe(true);
    enrollmentId = dbValue<number>('SELECT id FROM enrollment.enrollments WHERE student_id = ? AND status = 1', [studentId]);

    let subjects = Number(dbValue('SELECT count(*) FROM enrollment.enrollment_subjects WHERE enrollment_id = ?', [enrollmentId]));
    if (subjects === 0) {
        // Not automatic for a single enrollment: the curriculum is applied from «المناهج» (a queued job).
        const curriculum = dbValue<number>(`SELECT c.id FROM curriculum.curricula c JOIN enrollment.classes k ON k.grade_level_id = c.grade_level_id AND k.id = ?
            WHERE c.department_id = ? AND c.academic_year_id = ? AND c.status = 1`, [section.class_id, department.id, YEAR()]);
        expect((await attempt(page, 'POST', `/curriculum/curricula/${curriculum}/apply-to-enrollments`, {})).ok).toBe(true);
        runQueue();
        subjects = Number(dbValue('SELECT count(*) FROM enrollment.enrollment_subjects WHERE enrollment_id = ?', [enrollmentId]));
    }
    const required = Number(dbValue(`SELECT count(*) FROM curriculum.curriculum_subjects cs JOIN curriculum.curricula c ON c.id = cs.curriculum_id
        JOIN enrollment.classes k ON k.grade_level_id = c.grade_level_id AND k.id = ?
        WHERE c.department_id = ? AND c.academic_year_id = ? AND c.status = 1 AND cs.status = 1 AND cs.is_required`, [section.class_id, department.id, YEAR()]));
    expect(subjects, 'the curriculum subjects reach the enrollment').toBeGreaterThanOrEqual(required);
});

test("the student's timetable is the section's lessons", async ({ page }) => {
    await page.goto(`/timetable/students/${studentId}`);
    const { props } = await inertiaPage(page);
    const sectionLessons = Number(dbValue(`SELECT count(*) FROM timetable.schedules s JOIN enrollment.enrollments e ON e.section_id = s.section_id
        WHERE e.id = ? AND s.lifecycle_status = 1 AND s.group_id IS NULL`, [enrollmentId]));
    expect(sectionLessons).toBeGreaterThan(0);
    expect(JSON.stringify(props)).toContain(String(dbValue<string>("SELECT name FROM enrollment.sections WHERE id = (SELECT section_id FROM enrollment.enrollments WHERE id = ?)", [enrollmentId])));
});

test("a lesson sheet of the section lists the student and records them", async ({ page }) => {
    await page.goto('/attendance/review');
    const today = new Date().toISOString().slice(0, 10);
    const lesson = dbValue<number>(`SELECT s.id FROM timetable.schedules s JOIN enrollment.enrollments e ON e.section_id = s.section_id AND e.id = ?
        WHERE s.lifecycle_status = 1 AND s.group_id IS NULL AND s.week_no IS NULL AND s.day_of_week = extract(dow FROM ?::date) + 1
          AND NOT EXISTS (SELECT 1 FROM attendance.sessions x WHERE x.schedule_id = s.id AND x.session_date = ?::date)
        ORDER BY s.period_id LIMIT 1`, [enrollmentId, today, today]);
    test.skip(!lesson, `no unopened lesson of the section on ${today} (weekend or already opened)`);
    const opened = await send(page, 'POST', '/attendance/lessons/open', { schedule_id: lesson, date: today });
    const session = Number((opened.headers()['location'] ?? '').match(/sheets\/(\d+)/)?.[1]);
    expect(session, await opened.text()).toBeGreaterThan(0);
    const version = dbValue<number>('SELECT version FROM attendance.sessions WHERE id = ?', [session]);
    const submitted = await attempt(page, 'POST', `/attendance/sheets/${session}/submit`, { version, marks: [{ enrollment_id: enrollmentId, status: 3, minutes_late: 7 }], confirm: true });
    expect(submitted.ok, JSON.stringify(submitted.errors)).toBe(true);
    expect(dbQuery('SELECT status, minutes_late FROM attendance.records WHERE session_id = ? AND enrollment_id = ?', [session, enrollmentId])[0]).toEqual({ status: 3, minutes_late: 7 });
    expect(Number(dbValue('SELECT count(*) FROM attendance.records WHERE session_id = ?', [session])), 'the whole roster').toBe(
        Number(dbValue('SELECT count(*) FROM enrollment.enrollments WHERE section_id = (SELECT section_id FROM attendance.sessions WHERE id = ?) AND status = 1', [session])),
    );
});
