/**
 * «الإجازات والعطل»: a teacher files their own leave (never a colleague's, never decides it), the approver approves,
 * rejects or asks again; overlapping dates and a request over the policy's limit are refused; a two-level policy is
 * not finished by one approver; an approved leave shows on the staff day; a holiday added to the calendar stops
 * attendance on its day and taking it out of the calendar lets attendance happen again (never deleted).
 */
import { dbQuery, dbValue } from '../support/db';
import { RUN } from '../support/env';
import { attempt } from '../support/http';
import { expect, inertiaPage, test } from '../support/test';

test.describe.configure({ mode: 'serial' });

const SIMPLE_TYPE = 6; // one approval level, no document, no balance (current policy)
const TWO_LEVEL_TYPE = 1; // two approval levels, at most 15 days a request
const teacherId = () => dbValue<number>("SELECT id FROM teachers.teachers WHERE employee_code = 'T-S001'");
const YEAR = () => dbValue<number>('SELECT id FROM academic.academic_years WHERE is_current');

/** The first Sunday–Monday from March 2027 with no open/approved leave of T-S001 and no holiday. */
function freeWindow(skip = 0): { from: string; to: string } {
    const row = dbQuery<{ from: string; to: string }>(
        `SELECT d::date::text AS from, (d + interval '1 day')::date::text AS to
           FROM generate_series('2027-03-07'::date, '2027-06-20'::date, interval '7 day') d
          WHERE NOT EXISTS (SELECT 1 FROM leave.requests r WHERE r.teacher_id = ? AND r.status IN (1, 2, 3, 4)
                              AND daterange(r.date_from, r.date_to, '[]') && daterange(d::date, (d + interval '1 day')::date, '[]'))
            AND NOT EXISTS (SELECT 1 FROM academic.holidays h WHERE h.status = 1 AND daterange(h.start_date, h.end_date, '[]') && daterange(d::date, (d + interval '1 day')::date, '[]'))
          ORDER BY d OFFSET ? LIMIT 1`, [teacherId(), skip])[0];
    expect(row, 'a free window for the test leave').toBeTruthy();

    return row;
}
const request = (reason: string) => dbQuery<{ id: number; status: number; version: number; approved_levels: number }>(
    'SELECT id, status, version, approved_levels FROM leave.requests WHERE reason = ? ORDER BY id DESC LIMIT 1', [reason])[0];

let window = { from: '', to: '' };
const approvedReason = `إجازة اختبار ${RUN}`;

test('the teacher files and submits their own leave', async ({ pageAs }) => {
    const page = await pageAs('teacher');
    await page.goto('/leave/requests');
    window = freeWindow();
    const result = await attempt(page, 'POST', '/leave/requests', {
        academic_year_id: YEAR(), requester_kind: 1, person_id: teacherId(), leave_type_id: SIMPLE_TYPE,
        date_from: window.from, date_to: window.to, reason: approvedReason, submit: true,
    });
    expect(result.ok, JSON.stringify(result.errors)).toBe(true);
    expect(request(approvedReason)?.status, 'submitted → pending').toBe(2);
});

test("the teacher cannot file a colleague's leave nor decide their own", async ({ pageAs, watch }) => {
    watch.allow(/\/leave\//, 403);
    const page = await pageAs('teacher');
    await page.goto('/leave/requests');
    const colleague = dbValue<number>("SELECT id FROM teachers.teachers WHERE employee_code = 'T-S002'");
    const before = Number(dbValue('SELECT count(*) FROM leave.requests'));
    const forOther = await attempt(page, 'POST', '/leave/requests', {
        academic_year_id: YEAR(), requester_kind: 1, person_id: colleague, leave_type_id: SIMPLE_TYPE,
        date_from: window.from, date_to: window.to, reason: `بالنيابة ${RUN}`, submit: true,
    });
    expect(forOther.ok).toBe(false);
    expect(Number(dbValue('SELECT count(*) FROM leave.requests'))).toBe(before);

    const own = request(approvedReason);
    const decide = await attempt(page, 'POST', `/leave/requests/${own.id}/decide`, { version: own.version, decision: 'approve' });
    expect(decide.ok).toBe(false);
    expect(request(approvedReason).status, 'still pending').toBe(2);
});

test('the approver approves it and the staff day shows the teacher on leave', async ({ page }) => {
    await page.goto('/leave/requests');
    const own = request(approvedReason);
    const decided = await attempt(page, 'POST', `/leave/requests/${own.id}/decide`, { version: own.version, decision: 'approve', note: 'موافق' });
    expect(decided.ok, JSON.stringify(decided.errors)).toBe(true);
    expect(request(approvedReason).status).toBe(4);
    expect(Number(dbValue('SELECT count(*) FROM leave.request_events WHERE request_id = ?', [own.id])), 'history kept').toBeGreaterThanOrEqual(3);

    await page.goto(`/attendance/staff?date=${window.from}`);
    const { props } = await inertiaPage<{ day?: { rows?: { teacher_id: number; leave_request_no: unknown }[] }; rows?: { teacher_id: number; leave_request_no: unknown }[] }>(page);
    const rows = props.day?.rows ?? props.rows ?? [];
    expect(rows.find((r) => r.teacher_id === teacherId())?.leave_request_no).toBeTruthy();
});

test('overlapping dates are refused', async ({ pageAs }) => {
    const page = await pageAs('teacher');
    await page.goto('/leave/requests');
    const pending = () => Number(dbValue('SELECT count(*) FROM leave.requests WHERE status <> 1'));
    const before = pending();
    const reason = `متداخلة ${RUN}`;
    const result = await attempt(page, 'POST', '/leave/requests', {
        academic_year_id: YEAR(), requester_kind: 1, person_id: teacherId(), leave_type_id: SIMPLE_TYPE,
        date_from: window.to, date_to: window.to, reason, submit: true,
    });
    expect(result.ok).toBe(false);
    expect(JSON.stringify(result.errors)).toMatch(/overlap/);
    // By design the form's draft is kept (to be corrected) — it never reaches the approver.
    expect(request(reason)?.status, 'kept as a draft').toBe(1);
    expect(pending(), 'nothing submitted').toBe(before);
});

test('the request rules: date order, policy limit, missing reason', async ({ pageAs }) => {
    const page = await pageAs('teacher');
    await page.goto('/leave/requests');
    const w = freeWindow(1);
    const base = { academic_year_id: YEAR(), requester_kind: 1, person_id: teacherId(), leave_type_id: SIMPLE_TYPE, date_from: w.from, date_to: w.to, reason: `قواعد ${RUN}`, submit: true };
    const cases: [string, Record<string, unknown>][] = [
        ['end before start', { date_from: w.to, date_to: w.from }],
        ['more days than the policy allows (15)', { leave_type_id: TWO_LEVEL_TYPE, date_to: '2027-06-28' }],
        ['no reason', { reason: '' }],
        ['a school smuggled in', { school_id: 2 }],
        ['an unknown leave type', { leave_type_id: 999_999 }],
    ];
    for (const [label, change] of cases) {
        const before = Number(dbValue('SELECT count(*) FROM leave.requests'));
        const result = await attempt(page, 'POST', '/leave/requests', { ...base, ...change });
        expect(result.status, `${label}: no server error`).toBeLessThan(500);
        expect(result.ok, `${label} must be refused`).toBe(false);
        expect(Number(dbValue('SELECT count(*) FROM leave.requests')), `${label}: nothing written`).toBe(before);
    }
});

test('a two-level policy is not finished by one approver; rejecting needs no second step', async ({ pageAs, page }) => {
    const teacher = await pageAs('teacher');
    await teacher.goto('/leave/requests');
    const w = freeWindow(2);
    const reason = `مستويان ${RUN}`;
    expect((await attempt(teacher, 'POST', '/leave/requests', {
        academic_year_id: YEAR(), requester_kind: 1, person_id: teacherId(), leave_type_id: TWO_LEVEL_TYPE, date_from: w.from, date_to: w.to, reason, submit: true,
    })).ok).toBe(true);

    await page.goto('/leave/requests');
    let r = request(reason);
    expect((await attempt(page, 'POST', `/leave/requests/${r.id}/decide`, { version: r.version, decision: 'approve' })).ok).toBe(true);
    r = request(reason);
    expect(r.approved_levels).toBe(1);
    expect(r.status, 'waiting for the second level').toBe(2);
    const again = await attempt(page, 'POST', `/leave/requests/${r.id}/decide`, { version: r.version, decision: 'approve' });
    expect(again.ok, 'the same approver cannot give the second level').toBe(false);
    expect(request(reason).status).toBe(2);

    // The teacher withdraws the pending request: it ends without a decision.
    r = request(reason);
    expect((await attempt(teacher, 'POST', `/leave/requests/${r.id}/withdraw`, { version: r.version })).ok).toBe(true);
    expect(request(reason).status).toBe(7);
});

test('a holiday added to the calendar stops attendance; taken out of it, attendance is allowed again', async ({ page }) => {
    await page.goto('/leave/holidays');
    const day = dbValue<string>(
        `SELECT d::date::text FROM generate_series('2027-02-07'::date, '2027-06-20'::date, interval '1 day') d
          WHERE extract(dow FROM d) BETWEEN 0 AND 4 AND NOT EXISTS (SELECT 1 FROM academic.holidays h WHERE d::date BETWEEN h.start_date AND h.end_date)
          ORDER BY d OFFSET ? LIMIT 1`, [parseInt(RUN, 36) % 40]);
    const name = `عطلة اختبار ${RUN}`;
    const added = await attempt(page, 'POST', '/leave/holidays', { academic_year_id: YEAR(), name, start_date: day, end_date: day, holiday_type: 2 });
    expect(added.ok, JSON.stringify(added.errors)).toBe(true);
    const holiday = dbValue<number>('SELECT id FROM academic.holidays WHERE name = ?', [name]);
    await page.goto('/leave/holidays');
    await expect(page.getByText(name).first()).toBeVisible();

    const lesson = dbValue<number>(`SELECT id FROM timetable.schedules WHERE school_id = 1 AND lifecycle_status = 1 AND week_no IS NULL AND group_id IS NULL
        AND day_of_week = extract(dow FROM ?::date) + 1 ORDER BY id LIMIT 1`, [day]);
    const blocked = await attempt(page, 'POST', '/attendance/lessons/open', { schedule_id: lesson, date: day });
    expect(JSON.stringify(blocked.errors)).toContain('attendance.date_is_holiday');

    expect((await attempt(page, 'POST', `/leave/holidays/${holiday}/status`, { active: 0 })).ok).toBe(true);
    expect(Number(dbValue('SELECT count(*) FROM academic.holidays WHERE id = ?', [holiday])), 'never deleted').toBe(1);
    const allowed = await attempt(page, 'POST', '/attendance/lessons/open', { schedule_id: lesson, date: day });
    expect(JSON.stringify(allowed.errors)).not.toContain('date_is_holiday');
});
