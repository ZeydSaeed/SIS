/**
 * «المناهج»: subjects and curricula through the page's own endpoints — every rule at and past its boundary, a pass
 * mark above the subject's maximum, one active curriculum per department × grade × year, a subject linked with its
 * weekly hours (shown on the page), deactivation and reactivation (never deleted).
 */
import { dbQuery, dbValue } from '../support/db';
import { RUN } from '../support/env';
import { attempt } from '../support/http';
import { expect, test } from '../support/test';

test.describe.configure({ mode: 'serial' });

const YEAR = () => dbValue<number>('SELECT id FROM academic.academic_years WHERE is_current');
let n = 0;
const code = () => `S${RUN}${++n}`.slice(-20);

test('every rule of a new subject at and past its boundary', async ({ page }) => {
    // Long by design (9 boundary cases; ≈2 s per request + page + database check on one PHP server): every step still asserts.
    test.setTimeout(180_000);
    await page.goto('/curriculum');
    const base = () => ({ code: code(), name: `مادة ${RUN} ${n}`, subject_type: 1 });
    const cases: [string, Record<string, unknown>, boolean][] = [
        ['a theory subject', {}, true],
        ['a practical subject with credit hours 40', { subject_type: 3, credit_hours: 40 }, true],
        ['credit hours 41', { credit_hours: 41 }, false],
        ['type 4', { subject_type: 4 }, false],
        ['max grade 0', { max_grade: 0 }, false],
        ['pass grade above the maximum', { max_grade: 50, pass_grade: 60 }, false],
        ['code of 21 characters', { code: 'C'.repeat(21) }, false],
        ['name of 256 characters', { name: 'م'.repeat(256) }, false],
        ['status smuggled in', { status: 2 }, false],
    ];
    const outcomes: string[] = [];
    for (const [label, change, accepted] of cases) {
        const before = Number(dbValue('SELECT count(*) FROM curriculum.subjects'));
        const result = await attempt(page, 'POST', '/curriculum/subjects', { ...base(), ...change });
        expect(result.status, `${label}: no server error`).toBeLessThan(500);
        outcomes.push(`${label}: ${result.ok ? 'accepted' : 'refused ' + Object.values(result.errors).join(', ')}`);
        expect(result.ok, `${label} → ${JSON.stringify(result.errors)}`).toBe(accepted);
        expect(Number(dbValue('SELECT count(*) FROM curriculum.subjects')) - before, `${label}: rows written`).toBe(accepted ? 1 : 0);
    }
    test.info().annotations.push({ type: 'boundaries', description: outcomes.join(' · ') });
});

test('a duplicate subject name or code is refused', async ({ page }) => {
    await page.goto('/curriculum');
    const existing = dbQuery<{ code: string; name: string }>('SELECT code, name FROM curriculum.subjects WHERE status = 1 ORDER BY id LIMIT 1')[0];
    for (const [label, data] of [
        ['code', { code: existing.code, name: `اسم جديد ${RUN}`, subject_type: 1 }],
        ['name', { code: code(), name: existing.name, subject_type: 1 }],
    ] as const) {
        const before = Number(dbValue('SELECT count(*) FROM curriculum.subjects'));
        const result = await attempt(page, 'POST', '/curriculum/subjects', data);
        expect(result.ok, `duplicate ${label} must be refused`).toBe(false);
        expect(Number(dbValue('SELECT count(*) FROM curriculum.subjects'))).toBe(before);
    }
});

test('one active curriculum per department × grade × year', async ({ page }) => {
    await page.goto('/curriculum');
    const taken = dbQuery<{ department_id: number; grade_level_id: number }>(
        'SELECT department_id, grade_level_id FROM curriculum.curricula WHERE school_id = 1 AND status = 1 AND department_id IS NOT NULL AND academic_year_id = (SELECT id FROM academic.academic_years WHERE is_current) ORDER BY id LIMIT 1')[0];
    const before = Number(dbValue('SELECT count(*) FROM curriculum.curricula'));
    const result = await attempt(page, 'POST', '/curriculum/curricula', { academic_year_id: YEAR(), grade_level_id: taken.grade_level_id, department_id: taken.department_id, name: `منهج مكرر ${RUN}` });
    expect(result.ok).toBe(false);
    expect(JSON.stringify(result.errors)).toMatch(/curriculum_exists|موجود/);
    expect(Number(dbValue('SELECT count(*) FROM curriculum.curricula'))).toBe(before);
});

test('a subject linked with its weekly hours, deactivated and reactivated — never deleted', async ({ page }) => {
    await page.goto('/curriculum');
    const curriculum = dbValue<number>('SELECT id FROM curriculum.curricula WHERE school_id = 1 AND status = 1 ORDER BY id LIMIT 1');
    const subjectCode = code();
    expect((await attempt(page, 'POST', '/curriculum/subjects', { code: subjectCode, name: `مادة مرتبطة ${RUN}`, subject_type: 1 })).ok).toBe(true);
    const subject = dbValue<number>('SELECT id FROM curriculum.subjects WHERE code = ?', [subjectCode]);

    for (const [hours, ok] of [[0, false], [41, false], [3, true]] as const) {
        const linked = await attempt(page, 'POST', `/curriculum/curricula/${curriculum}/subjects`, { subject_id: subject, weekly_hours: hours, is_required: true });
        expect(linked.ok, `weekly hours ${hours}`).toBe(ok);
    }
    const link = dbQuery<{ id: number; weekly_hours: number; status: number }>('SELECT id, weekly_hours, status FROM curriculum.curriculum_subjects WHERE curriculum_id = ? AND subject_id = ?', [curriculum, subject])[0];
    expect(link).toMatchObject({ weekly_hours: 3, status: 1 });
    // Linking it again is refused (it used to answer «done» and silently keep the old hours).
    const again = await attempt(page, 'POST', `/curriculum/curricula/${curriculum}/subjects`, { subject_id: subject, weekly_hours: 2 });
    expect(again.ok, 'the same subject twice in a curriculum').toBe(false);
    expect(JSON.stringify(again.errors)).toContain('curriculum.subject_already_linked');
    expect(dbValue<number>('SELECT weekly_hours FROM curriculum.curriculum_subjects WHERE id = ?', [link.id])).toBe(3);

    await page.goto(`/curriculum/curricula/${curriculum}`);
    await expect(page.getByText(`مادة مرتبطة ${RUN}`).first()).toBeVisible();

    expect((await attempt(page, 'POST', `/curriculum/curriculum-subjects/${link.id}/deactivate`)).ok).toBe(true);
    expect(dbValue<number>('SELECT status FROM curriculum.curriculum_subjects WHERE id = ?', [link.id])).not.toBe(1);
    expect((await attempt(page, 'POST', `/curriculum/curriculum-subjects/${link.id}/reactivate`)).ok).toBe(true);
    expect(dbValue<number>('SELECT status FROM curriculum.curriculum_subjects WHERE id = ?', [link.id])).toBe(1);
    expect(Number(dbValue('SELECT count(*) FROM curriculum.curriculum_subjects WHERE id = ?', [link.id]))).toBe(1);
});
