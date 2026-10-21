/**
 * «الجدول الدراسي» — the seeded timetable of اعدادية التحرير is built in every state (complete, partly placed,
 * over-placed, a teacher who lost a subject, lessons in rooms too small for the class, a teacher over their limit).
 *
 * «اختبار الجدول» must show what the server finds; what the server finds must agree with independent SQL checks of
 * the grid; generation must be refused while a critical problem is open; a remedy applied from the report must fix
 * exactly that problem; a timetable with grid errors must never reach approval; and a clean grid goes through
 * approval and publication with no hard conflict in the published version.
 */
import type { Page } from '@playwright/test';
import { dbQuery, dbValue } from '../support/db';
import { RUN } from '../support/env';
import { attempt } from '../support/http';
import { expect, inertiaProps, test } from '../support/test';
import { notice, openDialog, ribbon } from '../support/ui';

test.describe.configure({ mode: 'serial' });

type Issue = { severity: string; code: string; schedule_ids: number[]; fixes: { code: string; params: Record<string, unknown> }[] };
type Report = { verdict: string; can_generate: boolean; counts: Record<string, number>; issues: Issue[] };

const YEAR = () => dbValue<number>('SELECT id FROM academic.academic_years WHERE is_current');

async function report(page: Page): Promise<Report> {
    if (!page.url().includes('/timetable')) {
        await page.goto('/timetable');
    }
    const props = await inertiaProps<{ testReport: Report }>(page, '/timetable?test=1', ['testReport']);

    return props.testReport;
}
const count = (r: Report, code: string) => r.issues.filter((i) => i.code === code).length;

/** Independent checks of the active grid (no application code involved). */
const SQL = {
    teacherDouble: `SELECT count(*) FROM (SELECT teacher_id, day_of_week, period_id FROM timetable.schedules
        WHERE school_id = 1 AND lifecycle_status = 1 AND week_no IS NULL AND joined_to_schedule_id IS NULL GROUP BY 1, 2, 3 HAVING count(*) > 1) q`,
    sectionDouble: `SELECT count(*) FROM (SELECT section_id, day_of_week, period_id FROM timetable.schedules
        WHERE school_id = 1 AND lifecycle_status = 1 AND week_no IS NULL AND group_id IS NULL GROUP BY 1, 2, 3 HAVING count(*) > 1) q`,
    roomDouble: `SELECT count(*) FROM (SELECT room_id, day_of_week, period_id FROM timetable.schedules
        WHERE school_id = 1 AND lifecycle_status = 1 AND room_id IS NOT NULL AND week_no IS NULL AND joined_to_schedule_id IS NULL GROUP BY 1, 2, 3 HAVING count(*) > 1) q`,
    roomTooSmall: `SELECT count(*) FROM timetable.schedules s JOIN organization.rooms r ON r.id = s.room_id
        WHERE s.school_id = 1 AND s.lifecycle_status = 1 AND s.group_id IS NULL AND s.joined_to_schedule_id IS NULL AND r.capacity IS NOT NULL
          AND (SELECT count(*) FROM enrollment.enrollments e WHERE e.section_id = s.section_id AND e.status = 1) > r.capacity`,
    teacherNotQualified: `SELECT count(*) FROM timetable.schedules s
        WHERE s.school_id = 1 AND s.lifecycle_status = 1
          AND NOT EXISTS (SELECT 1 FROM teachers.teacher_subjects ts WHERE ts.teacher_id = s.teacher_id AND ts.subject_id = s.subject_id
                          AND ts.academic_year_id = s.academic_year_id AND ts.school_id = s.school_id)`,
};
const sql = (q: string) => Number(dbValue(q));

test('«اختبار الجدول» shows the verdict and the counts the server computed', async ({ page }) => {
    await page.goto('/timetable');
    const server = await report(page);
    await ribbon(page, 'تحرير', /^فحص شامل قبل الإنشاء/);
    const window = openDialog(page, 'اختبار الجدول');
    await expect(window).toBeVisible();
    await expect(window.getByRole('button', { name: `حرجة: ${server.counts.critical}` })).toBeVisible();
    await expect(window.getByRole('button', { name: `أخطاء: ${server.counts.error}` })).toBeVisible();
    await expect(window.getByRole('button', { name: `تحذيرات: ${server.counts.warning}` })).toBeVisible();
    expect(server.verdict).toBe('blocked');
    await expect(window).toContainText('لا يمكن الإنشاء');
    // Every problem says why it happens.
    expect(await window.getByText('السبب:').count()).toBeGreaterThanOrEqual(server.counts.critical + server.counts.error);
});

test('what the report finds agrees with independent SQL checks of the grid', async ({ page }) => {
    const r = await report(page);
    expect(count(r, 'teacher_double_booked')).toBe(sql(SQL.teacherDouble));
    expect(count(r, 'section_double_booked')).toBe(sql(SQL.sectionDouble));
    expect(count(r, 'room_double_booked')).toBe(sql(SQL.roomDouble));
    expect(count(r, 'room_capacity_exceeded')).toBe(sql(SQL.roomTooSmall));
    expect(count(r, 'teacher_not_assigned')).toBe(sql(SQL.teacherNotQualified));
    // The seeded scenario holds both kinds of grid error.
    expect(sql(SQL.roomTooSmall)).toBeGreaterThan(0);
    expect(sql(SQL.teacherNotQualified)).toBeGreaterThan(0);
    test.info().annotations.push({ type: 'report', description: JSON.stringify(r.counts) });
});

test('generation is refused while a critical problem is open', async ({ page }) => {
    const r = await report(page);
    expect(r.can_generate).toBe(false);
    const before = Number(dbValue('SELECT count(*) FROM timetable.generation_runs'));
    const result = await attempt(page, 'POST', '/timetable/runs', { academic_year_id: YEAR(), mode: 1 });
    expect(result.ok).toBe(false);
    expect(result.errors.engine).toBe('timetable.generation_blocked');
    expect(Number(dbValue('SELECT count(*) FROM timetable.generation_runs'))).toBe(before);
});

test('a remedy applied from the report fixes that problem and nothing else', async ({ page }) => {
    const before = await report(page);
    // Whatever remedy the report computed for it: «change room» when a free room fits, else «remove the room».
    const target = before.issues.find((i) => i.code === 'room_capacity_exceeded' && i.fixes.some((f) => ['change_room', 'clear_room'].includes(f.code)));
    expect(target, 'a room-capacity error with a remedy').toBeTruthy();
    const scheduleId = target!.schedule_ids[0];

    await ribbon(page, 'تحرير', /^فحص شامل قبل الإنشاء/);
    const window = openDialog(page, 'اختبار الجدول');
    const item = window.locator('li, article, [data-issue-key], div').filter({ hasText: 'تتسع لـ' }).filter({ has: page.getByRole('button', { name: 'تطبيق' }) }).last();
    await item.getByRole('button', { name: 'تطبيق' }).first().click();
    const confirm = page.getByRole('dialog').filter({ hasText: /تأكيد|تطبيق/ }).last();
    if (await confirm.getByRole('button', { name: /تطبيق|تأكيد|نعم/ }).count()) {
        await confirm.getByRole('button', { name: /تطبيق|تأكيد|نعم/ }).last().click();
    }

    const after = async () => report(page);
    await expect.poll(async () => count(await after(), 'room_capacity_exceeded')).toBe(count(before, 'room_capacity_exceeded') - 1);
    // The repaired lesson (whichever the window listed last) now has no room or a room that holds its class.
    expect(sql(SQL.roomTooSmall)).toBe(count(before, 'room_capacity_exceeded') - 1);
    expect(scheduleId).toBeGreaterThan(0);
    const r = await after();
    expect(count(r, 'teacher_not_assigned'), 'other problems untouched').toBe(count(before, 'teacher_not_assigned'));
});

async function createVersion(page: Page, label: string): Promise<number> {
    const result = await attempt(page, 'POST', '/timetable/versions', { academic_year_id: YEAR(), name: `${label} ${RUN}` });
    expect(result.ok, JSON.stringify(result.errors)).toBe(true);

    return dbValue<number>('SELECT id FROM timetable.versions WHERE name = ?', [`${label} ${RUN}`]);
}

test('a timetable with lessons of an unqualified teacher cannot be submitted for approval', async ({ page }) => {
    const version = await createVersion(page, 'نسخة بأخطاء');
    const result = await attempt(page, 'POST', `/timetable/versions/${version}/submit`);
    expect(result.ok).toBe(false);
    expect(result.errors.engine).toBe('timetable.version_has_errors');
});

test('a timetable with lessons in rooms too small for the class cannot be submitted for approval', async ({ page }) => {
    // Take the unqualified teacher's lessons off the grid (soft cancel — history kept): only room errors remain.
    for (const row of dbQuery<{ id: number }>(SQL.teacherNotQualified.replace('SELECT count(*) FROM timetable.schedules s', 'SELECT s.id FROM timetable.schedules s'))) {
        const cancelled = await attempt(page, 'POST', `/timetable/schedules/${row.id}/cancel`);
        expect(cancelled.ok, JSON.stringify(cancelled.errors)).toBe(true);
    }
    expect(sql(SQL.teacherNotQualified)).toBe(0);
    expect(sql(SQL.roomTooSmall), 'room errors are still there').toBeGreaterThan(0);

    const version = await createVersion(page, 'نسخة بقاعات صغيرة');
    const result = await attempt(page, 'POST', `/timetable/versions/${version}/submit`);
    expect(result.ok, 'a grid with a room smaller than its class must not go to approval').toBe(false);
    expect(result.errors.engine).toBe('timetable.version_has_errors');
});

test('a clean grid goes through approval and publication with no hard conflict published', async ({ page }) => {
    // Clear the remaining too-small rooms (the report's «ترك الحصة بلا غرفة» remedy).
    for (const row of dbQuery<{ id: number; section_id: number; day_of_week: number; period_id: number; subject_id: number; teacher_id: number }>(
        SQL.roomTooSmall.replace('SELECT count(*) FROM timetable.schedules s', 'SELECT s.id, s.section_id, s.day_of_week, s.period_id, s.subject_id, s.teacher_id FROM timetable.schedules s'),
    )) {
        const cleared = await attempt(page, 'PATCH', `/timetable/schedules/${row.id}`, {
            academic_year_id: YEAR(), section_id: row.section_id, day_of_week: row.day_of_week, period_id: row.period_id,
            subject_id: row.subject_id, teacher_id: row.teacher_id, room_id: null,
        });
        expect(cleared.ok, JSON.stringify(cleared.errors)).toBe(true);
    }
    const r = await report(page);
    expect(r.counts.error, JSON.stringify(r.issues.filter((i) => i.severity === 'error').map((i) => i.code))).toBe(0);

    const version = await createVersion(page, 'نسخة سليمة');
    expect((await attempt(page, 'POST', `/timetable/versions/${version}/submit`)).ok).toBe(true);
    expect((await attempt(page, 'POST', `/timetable/versions/${version}/decide`, { decision: 'approve' })).ok).toBe(true);
    const published = await attempt(page, 'POST', `/timetable/versions/${version}/publish`, { effective_from: '2026-11-01' });
    expect(published.ok, JSON.stringify(published.errors)).toBe(true);
    expect(dbValue<number>('SELECT status FROM timetable.versions WHERE id = ?', [version])).toBe(5);

    const entries = (who: string) => Number(dbValue(`SELECT count(*) FROM (SELECT ${who}, day_of_week, period_id, coalesce(week_no, 0) FROM timetable.version_entries
        WHERE version_id = ? AND ${who} IS NOT NULL ${who === 'section_id' ? 'AND group_id IS NULL' : ''} GROUP BY 1, 2, 3, 4 HAVING count(*) > 1) q`, [version]));
    expect(entries('teacher_id'), 'teacher double-bookings published').toBe(0);
    expect(entries('section_id'), 'section double-bookings published').toBe(0);
    expect(entries('room_id'), 'room double-bookings published').toBe(0);
    expect(Number(dbValue(`SELECT count(*) FROM timetable.version_entries e JOIN organization.rooms r ON r.id = e.room_id
        WHERE e.version_id = ? AND e.group_id IS NULL AND r.capacity < (SELECT count(*) FROM enrollment.enrollments x WHERE x.section_id = e.section_id AND x.status = 1)`, [version])), 'rooms too small published').toBe(0);

    await page.goto('/timetable');
    await expect(notice(page, /خطأ/)).toHaveCount(0);
});
