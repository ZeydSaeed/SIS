/**
 * «إجلاس طلاب» on the exam page through the real browser and server: the manager seats class الأول / section B into
 * the fixture's scheduled session; only active students who take the session's subject are seated, a repeat seats
 * nobody twice, and a viewer neither sees the action nor can post it.
 *
 * Needs the exam fixture of `php artisan sis:e2e-seed-exams` (disposable sis_e2e_* copy only); skipped on sis_e2e.
 */
import fs from 'node:fs';
import path from 'node:path';
import { dbValue } from '../support/db';
import { E2E_DB } from '../support/env';
import { send } from '../support/http';
import { expect, test } from '../support/test';

type Fixture = {
    database: string;
    academic_year_id: number;
    sessions: { closed: number };
};

const MANIFEST = path.join('storage', 'app', 'e2e', 'exam-fixture.json');

const seats = (session: number) =>
    Number(
        dbValue(
            'SELECT count(*) FROM exams.exam_enrollments WHERE exam_session_id = ?',
            [session],
        ),
    );
/** Active section-B students of class الأول who take the session's subject and have no seat yet — computed independently. */
const expectedSeated = (session: number) =>
    Number(
        dbValue(
            `SELECT count(*) FROM enrollment.enrollments e
             JOIN enrollment.classes c ON c.id = e.class_id
             JOIN enrollment.sections s ON s.id = e.section_id
             JOIN exams.exam_sessions es ON es.id = ?
             WHERE c.code = 'CLS-1' AND s.code = 'SEC-B' AND e.status = 1 AND e.effective_to IS NULL
               AND e.school_id = es.school_id
               AND EXISTS (SELECT 1 FROM enrollment.enrollment_subjects x WHERE x.enrollment_id = e.id AND x.subject_id = es.subject_id AND x.status = 1)
               AND NOT EXISTS (SELECT 1 FROM exams.exam_enrollments ee WHERE ee.enrollment_id = e.id AND ee.exam_session_id = es.id)`,
            [session],
        ),
    );

test.describe('exam seating', () => {
    test.describe.configure({ mode: 'serial' });
    test.skip(
        E2E_DB === 'sis_e2e',
        'exam fixtures are only seeded on a disposable sis_e2e_* copy (php artisan sis:e2e-seed-exams)',
    );

    let f: Fixture;
    let examId: number;
    test.beforeAll(() => {
        f = JSON.parse(fs.readFileSync(MANIFEST, 'utf8')) as Fixture;
        expect(f.database, `${MANIFEST} belongs to another database`).toBe(
            E2E_DB,
        );
        examId = Number(
            dbValue('SELECT exam_id FROM exams.exam_sessions WHERE id = ?', [
                f.sessions.closed,
            ]),
        );
    });

    test('the manager seats a section through the form', async ({ page }) => {
        const before = seats(f.sessions.closed);
        const expected = expectedSeated(f.sessions.closed);
        await page.goto(`/exams/${examId}`);

        const row = page.locator('tbody tr', { hasText: '12:00' });
        await row.getByRole('button', { name: 'إجلاس طلاب' }).click();
        const form = page.locator('[data-window-id="exams.session.seat"]');
        await form.locator('select').nth(0).selectOption({ label: 'الأول' });
        await form.locator('select').nth(1).selectOption({ label: 'B' });
        await form.getByRole('button', { name: 'إجلاس', exact: true }).click();

        await expect(
            page.locator(`[data-seating-result="${f.sessions.closed}"]`),
        ).toContainText(`أُجلس ${expected}`);
        expect(seats(f.sessions.closed)).toBe(before + expected);
        await expect(
            page.locator('tbody tr', { hasText: '12:00' }),
        ).toContainText(String(before + expected));
    });

    test('seating the same section again seats nobody twice', async ({
        page,
    }) => {
        const before = seats(f.sessions.closed);
        await page.goto(`/exams/${examId}`);
        await page
            .locator('tbody tr', { hasText: '12:00' })
            .getByRole('button', { name: 'إجلاس طلاب' })
            .click();
        const form = page.locator('[data-window-id="exams.session.seat"]');
        await form.locator('select').nth(0).selectOption({ label: 'الأول' });
        await form.locator('select').nth(1).selectOption({ label: 'B' });
        await form.getByRole('button', { name: 'إجلاس', exact: true }).click();

        await expect(
            page.locator(`[data-seating-result="${f.sessions.closed}"]`),
        ).toContainText('أُجلس 0');
        expect(seats(f.sessions.closed)).toBe(before);
        expect(
            Number(
                dbValue(
                    'SELECT count(*) - count(DISTINCT enrollment_id) FROM exams.exam_enrollments WHERE exam_session_id = ?',
                    [f.sessions.closed],
                ),
            ),
        ).toBe(0);
    });

    test('a viewer has no seating action and cannot post it', async ({
        pageAs,
        watch,
    }) => {
        watch.allow(/\/exams\/\d+\/sessions\/\d+\/seat/, 403);
        const page = await pageAs('viewer');
        const before = seats(f.sessions.closed);
        const response = await page.goto(`/exams/${examId}`);
        expect(response?.status()).toBeLessThan(400);
        await expect(
            page.getByRole('button', { name: 'إجلاس طلاب' }),
        ).toHaveCount(0);

        const classId = Number(
            dbValue(
                "SELECT c.id FROM enrollment.classes c JOIN exams.exam_sessions es ON es.school_id = c.school_id WHERE es.id = ? AND c.code = 'CLS-1' ORDER BY c.academic_year_id DESC LIMIT 1",
                [f.sessions.closed],
            ),
        );
        const post = await send(
            page,
            'POST',
            `/exams/${examId}/sessions/${f.sessions.closed}/seat`,
            { class_id: classId },
        );
        expect(post.status()).toBe(403);
        expect(seats(f.sessions.closed)).toBe(before);
    });
});
