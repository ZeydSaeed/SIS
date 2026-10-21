/**
 * «الحضور والغياب» across the workflow and the modules around it: a teacher sees only their own day and cannot open a
 * colleague's lesson; the reviewer completes a lesson sheet and the records hold exactly the marks given (one per
 * student, no duplicate on a retry, a stale version refused); an active holiday stops attendance and a cancelled one
 * does not; an approved excuse turns the absence into «غياب بعذر» and a rejected one does not; a teacher on
 * approved leave (leave module) is recorded as such on the staff day.
 */
import { dbQuery, dbValue } from '../support/db';
import { attempt, send } from '../support/http';
import { expect, inertiaPage, test } from '../support/test';

test.describe.configure({ mode: 'serial' });

const TEACHER_CODE = 'T-S001';
const teacherId = () => dbValue<number>('SELECT id FROM teachers.teachers WHERE employee_code = ?', [TEACHER_CODE]);
/** A school day of the dataset with sheets (the seeder fills the last two weeks). */
const schoolDay = () => dbValue<string>("SELECT max(session_date)::text FROM attendance.sessions WHERE school_id = 1");

/** A timetable lesson on a seeded school day that nobody opened (the seeder leaves ~5 % unopened). */
function unopenedLesson(): { id: number; date: string; section_id: number } {
    return dbQuery<{ id: number; date: string; section_id: number }>(
        `SELECT s.id, d.session_date::text AS date, s.section_id FROM timetable.schedules s
           JOIN (SELECT DISTINCT session_date FROM attendance.sessions WHERE school_id = 1) d ON extract(dow FROM d.session_date) + 1 = s.day_of_week
          WHERE s.school_id = 1 AND s.lifecycle_status = 1 AND s.week_no IS NULL AND s.group_id IS NULL AND s.teacher_id <> ?
            AND NOT EXISTS (SELECT 1 FROM attendance.sessions x WHERE x.schedule_id = s.id AND x.session_date = d.session_date)
            AND (SELECT count(*) FROM enrollment.enrollments e WHERE e.section_id = s.section_id AND e.status = 1) >= 3
          ORDER BY d.session_date DESC, s.id LIMIT 1`, [teacherId()])[0];
}

test('the teacher sees only their own lessons of the day', async ({ pageAs }) => {
    const page = await pageAs('teacher');
    const day = schoolDay();
    // A teacher cannot widen the view to a colleague: teacher_id is honoured for reviewers only.
    const other = dbValue<number>("SELECT id FROM teachers.teachers WHERE employee_code = 'T-S002'");
    await page.goto(`/attendance/my?date=${day}&teacher_id=${other}`);
    const { props } = await inertiaPage<{ linked: boolean; day: { lessons?: { teacher_id: number; effective_teacher_id?: number }[] } }>(page);
    expect(props.linked).toBe(true);
    const lessons = props.day.lessons ?? [];
    expect(lessons.length, `T-S001 teaches on ${day}`).toBeGreaterThan(0);
    for (const lesson of lessons) {
        expect([lesson.teacher_id, lesson.effective_teacher_id]).toContain(teacherId());
    }
});

test("the teacher cannot open a colleague's lesson", async ({ pageAs, watch }) => {
    watch.allow(/lessons\/open/, 403);
    const page = await pageAs('teacher');
    const lesson = unopenedLesson();
    const before = Number(dbValue('SELECT count(*) FROM attendance.sessions'));
    const result = await attempt(page, 'POST', '/attendance/lessons/open', { schedule_id: lesson.id, date: lesson.date });
    expect(result.ok).toBe(false);
    expect(Number(dbValue('SELECT count(*) FROM attendance.sessions'))).toBe(before);
});

let sessionId = 0;
let marked: { absent: number; late: number; left: number; roster: number } = { absent: 0, late: 0, left: 0, roster: 0 };

test('the reviewer completes a lesson sheet and the records hold exactly the marks given', async ({ page }) => {
    const lesson = unopenedLesson();
    await page.goto('/attendance/review');
    const opened = await send(page, 'POST', '/attendance/lessons/open', { schedule_id: lesson.id, date: lesson.date });
    expect(opened.status(), await opened.text()).toBe(302);
    sessionId = Number((opened.headers()['location'] ?? '').match(/sheets\/(\d+)/)?.[1]);
    expect(sessionId).toBeGreaterThan(0);

    // The sheet page lists the section's students.
    await page.goto(`/attendance/sheets/${sessionId}`);
    const roster = dbQuery<{ id: number }>('SELECT id FROM enrollment.enrollments WHERE section_id = ? AND status = 1 ORDER BY id', [lesson.section_id]);
    marked = { absent: roster[0].id, late: roster[1].id, left: roster[2].id, roster: roster.length };
    const version = dbValue<number>('SELECT version FROM attendance.sessions WHERE id = ?', [sessionId]);

    const marks = [
        { enrollment_id: marked.absent, status: 2 },
        { enrollment_id: marked.late, status: 3, minutes_late: 12 },
        { enrollment_id: marked.left, status: 5, note: 'مغادرة بإذن' },
    ];
    const submitted = await attempt(page, 'POST', `/attendance/sheets/${sessionId}/submit`, { version, marks, confirm: true });
    expect(submitted.ok, JSON.stringify(submitted.errors)).toBe(true);

    const records = dbQuery<{ enrollment_id: number; status: number; minutes_late: number | null }>(
        'SELECT enrollment_id, status, minutes_late FROM attendance.records WHERE session_id = ? ORDER BY enrollment_id', [sessionId]);
    expect(records.length, 'one record per student').toBe(marked.roster);
    const by = new Map(records.map((r) => [r.enrollment_id, r]));
    expect(by.get(marked.absent)?.status).toBe(2);
    expect(by.get(marked.late)).toMatchObject({ status: 3, minutes_late: 12 });
    expect(by.get(marked.left)?.status).toBe(5);
    expect(records.filter((r) => r.status === 1).length, 'everyone else present').toBe(marked.roster - 3);
    expect(dbValue<number>('SELECT sheet_status FROM attendance.sessions WHERE id = ?', [sessionId])).toBe(2);
});

test('a stale version and a second submit are refused — no duplicate records', async ({ page }) => {
    await page.goto(`/attendance/sheets/${sessionId}`);
    const before = Number(dbValue('SELECT count(*) FROM attendance.records WHERE session_id = ?', [sessionId]));
    const stale = await attempt(page, 'POST', `/attendance/sheets/${sessionId}/submit`, { version: 1, marks: [{ enrollment_id: marked.absent, status: 1 }], confirm: true });
    expect(stale.ok).toBe(false);
    expect(Number(dbValue('SELECT count(*) FROM attendance.records WHERE session_id = ?', [sessionId]))).toBe(before);
    expect(dbValue<number>('SELECT status FROM attendance.records WHERE session_id = ? AND enrollment_id = ?', [sessionId, marked.absent])).toBe(2);
});

test('submitting without confirming is refused with its message', async ({ page }) => {
    const lesson = unopenedLesson();
    await page.goto('/attendance/review');
    const opened = await send(page, 'POST', '/attendance/lessons/open', { schedule_id: lesson.id, date: lesson.date });
    const id = Number((opened.headers()['location'] ?? '').match(/sheets\/(\d+)/)?.[1]);
    const version = dbValue<number>('SELECT version FROM attendance.sessions WHERE id = ?', [id]);
    const result = await attempt(page, 'POST', `/attendance/sheets/${id}/submit`, { version, marks: [] });
    expect(result.ok).toBe(false);
    expect(result.errors.confirm).toBe('attendance.submit_confirm_required');
    expect(dbValue<number>('SELECT sheet_status FROM attendance.sessions WHERE id = ?', [id]), 'still a draft').toBe(1);
});

test('an active holiday stops attendance, a cancelled one does not', async ({ page }) => {
    await page.goto('/attendance/review');
    const onDay = (date: string) => dbValue<number>(
        `SELECT s.id FROM timetable.schedules s WHERE s.school_id = 1 AND s.lifecycle_status = 1 AND s.week_no IS NULL AND s.group_id IS NULL
            AND s.day_of_week = extract(dow FROM ?::date) + 1 ORDER BY s.id LIMIT 1`, [date]);
    const active = dbQuery<{ start_date: string }>("SELECT start_date::text FROM academic.holidays WHERE status = 1 AND extract(dow FROM start_date) BETWEEN 0 AND 4 ORDER BY start_date LIMIT 1")[0];
    const holiday = await attempt(page, 'POST', '/attendance/lessons/open', { schedule_id: onDay(active.start_date), date: active.start_date });
    expect(holiday.ok).toBe(false);
    expect(JSON.stringify(holiday.errors)).toContain('attendance.date_is_holiday');

    const cancelled = dbQuery<{ start_date: string }>('SELECT start_date::text FROM academic.holidays WHERE status = 2 ORDER BY start_date LIMIT 1')[0];
    const allowed = await attempt(page, 'POST', '/attendance/lessons/open', { schedule_id: onDay(cancelled.start_date), date: cancelled.start_date });
    expect(JSON.stringify(allowed.errors), 'a cancelled holiday is a school day').not.toContain('date_is_holiday');
});

test('an approved excuse turns the absence into «غياب بعذر», a rejected one does not', () => {
    // An excuse covers days: the lessons the student attended that day stay «حاضر»; only absences change.
    const inRange = (excuseStatus: number, recordStatus: number) => Number(dbValue(
        `SELECT count(*) FROM attendance.excuses x JOIN attendance.records r ON r.enrollment_id = x.enrollment_id AND r.attendance_date BETWEEN x.date_from AND x.date_to
          WHERE x.status = ? AND x.school_id = 1 AND r.status = ?`, [excuseStatus, recordStatus]));
    const linked = (excuseStatus: number, recordStatus?: number) => Number(dbValue(
        `SELECT count(*) FROM attendance.excuses x JOIN attendance.records r ON r.excuse_id = x.id WHERE x.status = ? AND x.school_id = 1
          ${recordStatus === undefined ? '' : 'AND r.status = ' + recordStatus}`, [excuseStatus]));

    expect(Number(dbValue('SELECT count(*) FROM attendance.excuses WHERE status = 2 AND school_id = 1')), 'the dataset has approved excuses').toBeGreaterThan(0);
    expect(inRange(2, 2), 'no absence left under an approved excuse').toBe(0);
    expect(linked(2), 'approved excuses changed records').toBeGreaterThan(0);
    expect(linked(2, 4), 'every record an approved excuse touched is «غياب بعذر»').toBe(linked(2));

    expect(inRange(3, 2), 'a rejected excuse leaves the absence').toBeGreaterThan(0);
    expect(linked(3), 'a rejected excuse changes nothing').toBe(0);
});

test('a teacher on approved leave is shown and recorded as such on the staff day', async ({ page }) => {
    const row = dbQuery<{ teacher_id: number; work_date: string; request_no: string }>(
        `SELECT s.teacher_id, s.work_date::text, l.request_no FROM attendance.staff_records s
           JOIN leave.requests l ON l.teacher_id = s.teacher_id AND s.work_date BETWEEN l.date_from AND l.date_to AND l.status = 4
          WHERE s.school_id = 1 AND s.status = 5 ORDER BY s.work_date DESC LIMIT 1`)[0];
    expect(row, 'the dataset has a teacher on approved leave on a recorded day').toBeTruthy();
    // Every «approved leave» day is backed by an approved request.
    expect(Number(dbValue(`SELECT count(*) FROM attendance.staff_records s WHERE s.school_id = 1 AND s.status = 5 AND NOT EXISTS (
        SELECT 1 FROM leave.requests l WHERE l.teacher_id = s.teacher_id AND s.work_date BETWEEN l.date_from AND l.date_to AND l.status = 4)`))).toBe(0);

    await page.goto(`/attendance/staff?date=${row.work_date}`);
    const { props } = await inertiaPage<{ day?: { rows?: { teacher_id: number; leave_request_no: number | string | null }[] }; rows?: { teacher_id: number; leave_request_no: number | string | null }[] }>(page);
    const rows = props.day?.rows ?? props.rows ?? [];
    expect(rows.find((r) => r.teacher_id === row.teacher_id)?.leave_request_no, 'the staff day names the leave request').toBeTruthy();
});
