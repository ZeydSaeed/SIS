/**
 * Server-side authorization and school isolation, through the real session of each persona:
 * no role → refused everywhere; viewer → reads, every write refused (and nothing written); اعدادية العباس's
 * manager → never sees or opens a record of اعدادية التحرير; signed out → login; no self-service roles.
 */
import { dbValue } from '../support/db';
import { send } from '../support/http';
import { expect, inertiaPage, test } from '../support/test';

const MODULE_PAGES = [
    '/students', '/enrollments', '/admission', '/curriculum', '/teachers', '/organization/rooms', '/organization/classes-sections',
    '/timetable', '/attendance', '/attendance/staff', '/attendance/excuses', '/leave', '/leave/requests', '/leave/holidays', '/reports',
];

const schoolOneStudent = () => dbValue<string>("SELECT full_name FROM students.students WHERE school_id = 1 AND status = 1 ORDER BY id LIMIT 1");

test.describe('no role', () => {
    for (const url of MODULE_PAGES) {
        test(`refused: ${url}`, async ({ pageAs, watch }) => {
            watch.allow(/.*/, 403);
            const page = await pageAs('noroles');
            const response = await page.goto(url);
            expect(response?.status()).toBe(403);
            await expect(page.locator('body')).not.toContainText(schoolOneStudent());
        });
    }

    test('the self-service role bootstrap is refused and grants nothing', async ({ pageAs, watch }) => {
        watch.allow(/context\/ops-bootstrap/, 403);
        const page = await pageAs('noroles');
        const response = await send(page, 'POST', '/context/ops-bootstrap');
        expect(response.status()).toBe(403);
        expect(Number(dbValue("SELECT count(*) FROM security.user_roles r JOIN public.users u ON u.id = r.user_id WHERE u.email = 'e2e-noroles@sis.test'"))).toBe(0);
    });
});

test.describe('signed out', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    for (const url of ['/students', '/teachers', '/attendance', '/leave']) {
        test(`login required: ${url}`, async ({ page }) => {
            await page.goto(url);
            await expect(page).toHaveURL(/\/login$/);
        });
    }
});

test.describe('viewer', () => {
    test('reads the module pages without errors', async ({ pageAs, watch }) => {
        const page = await pageAs('viewer');
        const refused: string[] = [];
        watch.allow(/.*/, 403);
        for (const url of MODULE_PAGES) {
            const response = await page.goto(url);
            const status = response?.status() ?? 0;
            expect(status, url).toBeLessThan(500);
            if (status === 403) {
                refused.push(url);
            }
        }
        // Modules without a viewer role are refused, not half-rendered.
        test.info().annotations.push({ type: 'viewer refused', description: refused.join(' ') || 'none' });
        expect(refused).not.toContain('/students');
        expect(refused).not.toContain('/teachers');
    });

    const writes: [string, 'POST' | 'PATCH' | 'PUT', () => string, Record<string, unknown>, string][] = [
        ['register a teacher', 'POST', () => '/teachers', { employee_code: 'T-V001', first_name: 'مستعرض', last_name: 'محاولة', national_id: '199999999901' }, "SELECT count(*) FROM teachers.teachers"],
        ['rename a teacher', 'PATCH', () => `/teachers/${dbValue("SELECT id FROM teachers.teachers WHERE employee_code = 'T-S002'")}`, { first_name: 'مغيّر', last_name: 'محاولة' }, "SELECT count(*) FROM teachers.teachers WHERE first_name = 'مغيّر'"],
        ['add a student', 'POST', () => '/students', { first_name: 'محاولة', last_name: 'مستعرض', national_id: '200099999901', birth_date: '2010-01-01', gender: 1 }, 'SELECT count(*) FROM students.students'],
        ['add a room', 'POST', () => '/organization/rooms', { name: 'قاعة المستعرض', capacity: 30 }, 'SELECT count(*) FROM organization.rooms'],
        ['add a subject', 'POST', () => '/curriculum/subjects', { name: 'مادة المستعرض', code: 'V-001' }, 'SELECT count(*) FROM curriculum.subjects'],
        ['deactivate a teacher', 'POST', () => `/teachers/${dbValue("SELECT id FROM teachers.teachers WHERE employee_code = 'T-S003'")}/deactivate`, {}, 'SELECT count(*) FROM teachers.teachers WHERE status = 1'],
        ['record staff attendance', 'POST', () => '/attendance/staff', { teacher_id: dbValue("SELECT id FROM teachers.teachers WHERE employee_code = 'T-S004'"), work_date: '2026-10-20', status: 1 }, 'SELECT count(*) FROM attendance.staff_records'],
    ];
    for (const [label, method, url, data, countSql] of writes) {
        test(`write refused: ${label}`, async ({ pageAs, watch }) => {
            watch.allow(/.*/, 403);
            const page = await pageAs('viewer');
            const before = dbValue(countSql);
            const response = await send(page, method, url(), data);
            expect(response.status(), await response.text()).toBe(403);
            expect(dbValue(countSql)).toBe(before);
        });
    }
});

test.describe('school isolation (manager of اعدادية العباس)', () => {
    test('the students list holds no student of اعدادية التحرير', async ({ pageAs }) => {
        const page = await pageAs('school2');
        await page.goto('/students');
        await page.waitForLoadState('networkidle');
        const { props } = await inertiaPage<Record<string, unknown>>(page);
        expect(JSON.stringify(props)).not.toContain(schoolOneStudent());
        await expect(page.locator('body')).not.toContainText(schoolOneStudent());
    });

    test('the teachers list holds only teachers of its own school', async ({ pageAs }) => {
        const page = await pageAs('school2');
        await page.goto('/teachers');
        await page.waitForLoadState('networkidle');
        const onlySchoolOne = dbValue<string>(
            "SELECT t.full_name FROM teachers.teachers t WHERE NOT EXISTS (SELECT 1 FROM teachers.teacher_schools ts WHERE ts.teacher_id = t.id AND ts.school_id = 2) ORDER BY t.id LIMIT 1",
        );
        const own = dbValue<string>("SELECT full_name FROM teachers.teachers WHERE employee_code = 'T-E077'");
        const { props } = await inertiaPage(page);
        expect(JSON.stringify(props)).not.toContain(onlySchoolOne);
        expect(JSON.stringify(props)).toContain(own);
    });

    const foreign: [string, () => string][] = [
        ['a student', () => `/students/${dbValue('SELECT id FROM students.students WHERE school_id = 1 ORDER BY id LIMIT 1')}`],
        ['a teacher', () => `/teachers/${dbValue("SELECT id FROM teachers.teachers WHERE employee_code = 'T-S002'")}`],
        ['an enrollment', () => `/enrollments/${dbValue('SELECT id FROM enrollment.enrollments WHERE school_id = 1 ORDER BY id LIMIT 1')}`],
        ['a curriculum', () => `/curriculum/curricula/${dbValue('SELECT id FROM curriculum.curricula WHERE school_id = 1 ORDER BY id LIMIT 1')}`],
        ['an application', () => `/admission/follow-up/applications/${dbValue('SELECT id FROM admission.applications WHERE school_id = 1 ORDER BY id LIMIT 1')}`],
        ['an attendance sheet', () => `/attendance/sheets/${dbValue('SELECT id FROM attendance.sessions WHERE school_id = 1 ORDER BY id LIMIT 1')}`],
        ['a lesson', () => `/timetable/${dbValue('SELECT id FROM timetable.schedules WHERE school_id = 1 ORDER BY id LIMIT 1')}`],
        ['a student timetable', () => `/timetable/students/${dbValue('SELECT id FROM students.students WHERE school_id = 1 ORDER BY id LIMIT 1')}`],
    ];
    for (const [label, url] of foreign) {
        test(`cannot open ${label} of اعدادية التحرير by id`, async ({ pageAs, watch }) => {
            watch.allow(/.*/, 403);
            watch.allow(/.*/, 404);
            const page = await pageAs('school2');
            const response = await page.goto(url());
            expect([403, 404], `${url()} → ${response?.status()}`).toContain(response?.status());
            await expect(page.locator('body')).not.toContainText(schoolOneStudent());
        });
    }

    test('cannot switch its session to اعدادية التحرير', async ({ pageAs, watch }) => {
        watch.allow(/context\/school/, 422);
        const page = await pageAs('school2');
        const response = await send(page, 'POST', '/context/school', { school_id: 1 });
        expect(response.status()).toBe(422);
        expect((await response.json()).errors.school_id[0]).toContain('المدرسة غير مسموحة');
    });

    test('cannot change a teacher of اعدادية التحرير', async ({ pageAs, watch }) => {
        watch.allow(/.*/, 403);
        watch.allow(/.*/, 404);
        const page = await pageAs('school2');
        const id = dbValue("SELECT id FROM teachers.teachers WHERE employee_code = 'T-S002'");
        const before = dbValue('SELECT full_name FROM teachers.teachers WHERE id = ?', [id]);
        const response = await send(page, 'PATCH', `/teachers/${id}`, { first_name: 'مخترق', last_name: 'محاولة' });
        // Web pages answer a refused write by redirecting back with the error code in the session.
        expect([302, 403, 404]).toContain(response.status());
        expect(dbValue('SELECT full_name FROM teachers.teachers WHERE id = ?', [id])).toBe(before);
        if (response.status() === 302) {
            await page.goto('/teachers');
            const { props } = await inertiaPage<{ errors: Record<string, string> }>(page);
            expect(props.errors.teacher).toMatch(/not_found|not_in_school|forbidden/);
        }
    });
});
