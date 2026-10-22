/**
 * «إدخال درجات الجلسة» (/grades/sheet) through the real browser and the real server:
 * a grade teacher sees and grades only the seats of their own teaching assignment; uncovered and other-school sessions
 * are refused; tampered seat / session ids, existing grades, repeated submissions, invalid scores and a session that is
 * not open are all refused by the server; the manager sees the whole session; users without grade entry are refused.
 *
 * Needs the exam fixture of `php artisan sis:e2e-seed-exams` (a disposable sis_e2e_* copy only — it refuses sis and
 * sis_e2e). On the shared sis_e2e dataset the file is skipped with that reason, never silently.
 */
import fs from 'node:fs';
import path from 'node:path';
import type { Browser, Page } from '@playwright/test';
import { dbQuery, dbValue } from '../support/db';
import { BASE_URL, E2E_DB, personaPassword } from '../support/env';
import { send } from '../support/http';
import { expect, inertiaPage, test, type PageWatch } from '../support/test';

type Fixture = {
    database: string;
    tag: string;
    grader_email: string;
    academic_year_id: number;
    sessions: {
        open: number;
        closed: number;
        uncovered: number;
        foreign: number;
    };
    seats: Record<
        | 'graded'
        | 'ui_score'
        | 'ui_absent'
        | 'replay'
        | 'other_section'
        | 'other_section_2'
        | 'closed'
        | 'uncovered',
        number
    >;
};
type Outcome = {
    exam_enrollment_id: number;
    status: string;
    grade_id: number | null;
};
type SheetProps = {
    rows: {
        exam_enrollment_id: number;
        student_name: string;
        grade_id: number | null;
    }[];
    outcomes: Outcome[] | null;
    scope: { school_wide: boolean };
};

const MANIFEST = path.join('storage', 'app', 'e2e', 'exam-fixture.json');

function loadFixture(): Fixture {
    const fixture = JSON.parse(fs.readFileSync(MANIFEST, 'utf8')) as Fixture;
    if (fixture.database !== E2E_DB) {
        throw new Error(
            `${MANIFEST} was written for «${fixture.database}», not «${E2E_DB}» — run sis:e2e-seed-exams on this database.`,
        );
    }

    return fixture;
}

const sheetUrl = (f: Fixture, session: number) =>
    `/grades/sheet?academic_year_id=${f.academic_year_id}&session_id=${session}`;
const gradeRows = (seat: number) =>
    dbQuery<{
        score: string | null;
        is_absent: boolean;
        status: number;
        is_current: boolean;
        entered_by: number | null;
    }>(
        'SELECT score, is_absent, status, is_current, entered_by FROM exams.student_grades WHERE exam_enrollment_id = ? ORDER BY id',
        [seat],
    );
const studentName = (seat: number) =>
    dbValue<string>(
        'SELECT st.full_name FROM exams.exam_enrollments ee JOIN enrollment.enrollments e ON e.id = ee.enrollment_id JOIN students.students st ON st.id = e.student_id WHERE ee.id = ?',
        [seat],
    );

/** The synthetic grade teacher of the fixture, signed in through the real login form. */
const GRADER_STATE = path.join('playwright', '.auth', 'exam-grader.json');

/** Signs the grade teacher in once per run (the login form allows 5 attempts a minute) and keeps the session. */
async function signInGrader(browser: Browser, f: Fixture): Promise<void> {
    const context = await browser.newContext({
        baseURL: BASE_URL,
        locale: 'ar-IQ',
        timezoneId: 'Asia/Baghdad',
        storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(f.grader_email);
    await page.locator('input[name="password"]').fill(personaPassword());
    await page.locator('[data-test="login-button"]').click();
    await expect(page).not.toHaveURL(/\/login(\?|$)/, { timeout: 30_000 });
    await context.storageState({ path: GRADER_STATE });
    await context.close();
}

/** A page of the signed-in grade teacher, watched like every other page. */
async function graderPage(
    browser: Browser,
    f: Fixture,
    watch: PageWatch,
): Promise<Page> {
    const context = await browser.newContext({
        baseURL: BASE_URL,
        locale: 'ar-IQ',
        timezoneId: 'Asia/Baghdad',
        storageState: GRADER_STATE,
    });
    const page = await context.newPage();
    watch.attach(page);
    await page.goto(sheetUrl(f, f.sessions.open));
    await expect(page).not.toHaveURL(/\/login(\?|$)/);

    return page;
}

/** POST the sheet from the signed-in page; returns the HTTP status and the per-row outcomes the redirect shows. */
async function postSheet(
    page: Page,
    f: Fixture,
    session: number,
    rows: Record<string, unknown>[],
    key?: string,
): Promise<{ status: number; outcomes: Record<number, string> }> {
    await page.goto(sheetUrl(f, f.sessions.open));
    const response = await send(
        page,
        'POST',
        `/grades/sheet/${session}`,
        { rows },
        key,
    );
    const status = response.status();
    if (status !== 302) {
        return { status, outcomes: {} };
    }
    await page.goto(
        response.headers()['location'] ?? sheetUrl(f, f.sessions.open),
    );
    const props = (await inertiaPage<SheetProps>(page)).props;

    return {
        status,
        outcomes: Object.fromEntries(
            (props.outcomes ?? []).map((o) => [o.exam_enrollment_id, o.status]),
        ),
    };
}

test.describe('grade-entry sheet', () => {
    test.describe.configure({ mode: 'serial' });
    test.skip(
        E2E_DB === 'sis_e2e',
        'exam fixtures are only seeded on a disposable sis_e2e_* copy (php artisan sis:e2e-seed-exams)',
    );

    let f: Fixture;
    test.beforeAll(async ({ browser }) => {
        f = loadFixture();
        await signInGrader(browser, f);
        // A fresh fixture: only the pre-graded seat has a grade (re-run sis:e2e-seed-exams before the spec).
        const graded = Object.entries(f.seats)
            .filter(([, seat]) => gradeRows(seat).length > 0)
            .map(([name]) => name);
        expect(graded, `fixture ${f.tag} is not fresh`).toEqual(['graded']);
    });

    test('the grade teacher sees only the sessions and students of their assignment', async ({
        browser,
        watch,
    }) => {
        const page = await graderPage(browser, f, watch);
        await page.goto(sheetUrl(f, f.sessions.open));
        const props = (await inertiaPage<SheetProps>(page)).props;

        expect(props.scope.school_wide).toBe(false);
        expect(
            props.rows.map((r) => r.exam_enrollment_id).sort((x, y) => x - y),
        ).toEqual(
            [
                f.seats.graded,
                f.seats.ui_score,
                f.seats.ui_absent,
                f.seats.replay,
            ].sort((x, y) => x - y),
        );
        const table = page.locator('[data-window-id="grades.sheet"] tbody');
        await expect(table).toContainText(studentName(f.seats.ui_score));
        await expect(table).not.toContainText(
            studentName(f.seats.other_section),
        );
        await expect(
            page.locator('[data-window-id="grades.sheet"] dl'),
        ).toContainText('طلاب تكليفاتك فقط');
        // The pre-graded student is read-only with the stored value.
        const graded = table.locator('tr', {
            hasText: studentName(f.seats.graded),
        });
        await expect(graded).toContainText('70.00');
        await expect(graded.locator('input')).toHaveCount(0);
    });

    test('an uncovered session and another school’s session are refused', async ({
        browser,
        watch,
    }) => {
        watch.allow(/\/grades\/sheet/, 403);
        watch.allow(/\/grades\/sheet/, 404);
        const page = await graderPage(browser, f, watch);

        expect(
            (await page.goto(sheetUrl(f, f.sessions.uncovered)))?.status(),
        ).toBe(403);
        await expect(page.locator('body')).not.toContainText(
            studentName(f.seats.uncovered),
        );
        expect(
            (await page.goto(sheetUrl(f, f.sessions.foreign)))?.status(),
        ).toBe(404);
        expect(
            Number(
                dbValue(
                    "SELECT count(*) FROM security.security_audit_logs WHERE action = 'grades.sheet.view' AND event_id = 'SEC_IDOR_BLOCKED' AND target_id = ?",
                    [String(f.sessions.uncovered)],
                ),
            ),
        ).toBeGreaterThan(0);
    });

    test('an out-of-range score is flagged in the page and refused by the server', async ({
        browser,
        watch,
    }) => {
        watch.allow(/\/grades\/sheet\/\d+/, 422);
        const page = await graderPage(browser, f, watch);
        await page.goto(sheetUrl(f, f.sessions.open));
        const row = page.locator('tbody tr', {
            hasText: studentName(f.seats.ui_score),
        });
        await row.locator('input[type="number"]').fill('150');
        await expect(row).toContainText('خارج المدى');
        await expect(
            page.getByRole('button', { name: /حفظ الدرجات/ }),
        ).toBeDisabled();

        const above = await postSheet(page, f, f.sessions.open, [
            {
                exam_enrollment_id: f.seats.ui_score,
                score: '150',
                is_absent: false,
            },
        ]);
        expect(above.outcomes[f.seats.ui_score]).toBe('grades.validation');
        const negative = await send(
            page,
            'POST',
            `/grades/sheet/${f.sessions.open}`,
            {
                rows: [
                    {
                        exam_enrollment_id: f.seats.ui_score,
                        score: '-1',
                        is_absent: false,
                    },
                ],
            },
        );
        expect(negative.status()).toBe(422);
        expect(gradeRows(f.seats.ui_score)).toHaveLength(0);
    });

    test('the grade teacher enters grades for assigned students in the page', async ({
        browser,
        watch,
    }) => {
        const page = await graderPage(browser, f, watch);
        await page.goto(sheetUrl(f, f.sessions.open));
        const scoreRow = page.locator('tbody tr', {
            hasText: studentName(f.seats.ui_score),
        });
        const absentRow = page.locator('tbody tr', {
            hasText: studentName(f.seats.ui_absent),
        });

        await scoreRow.locator('input[type="number"]').fill('88.5');
        await absentRow.locator('input[type="checkbox"]').check();
        await expect(absentRow.locator('input[type="number"]')).toBeDisabled();
        const save = page.getByRole('button', { name: /حفظ الدرجات \(2\)/ });
        await expect(save).toBeEnabled();
        await save.click();

        await expect(scoreRow).toContainText('88.50');
        await expect(scoreRow).toContainText('حُفظت');
        await expect(absentRow).toContainText('غائب');
        const graderId = Number(
            dbValue('SELECT id FROM public.users WHERE email = ?', [
                f.grader_email,
            ]),
        );
        expect(gradeRows(f.seats.ui_score)).toEqual([
            {
                score: '88.50',
                is_absent: false,
                status: 2,
                is_current: true,
                entered_by: graderId,
            },
        ]);
        expect(gradeRows(f.seats.ui_absent)).toEqual([
            {
                score: null,
                is_absent: true,
                status: 2,
                is_current: true,
                entered_by: graderId,
            },
        ]);
        // The replay seat was left empty, so nothing was sent for it.
        expect(gradeRows(f.seats.replay)).toHaveLength(0);
    });

    test('an existing grade is never overwritten', async ({
        browser,
        watch,
    }) => {
        const page = await graderPage(browser, f, watch);
        const result = await postSheet(page, f, f.sessions.open, [
            {
                exam_enrollment_id: f.seats.graded,
                score: '95',
                is_absent: false,
            },
        ]);

        expect(result.outcomes[f.seats.graded]).toBe('grades.conflict');
        expect(gradeRows(f.seats.graded).map((g) => g.score)).toEqual([
            '70.00',
        ]);
    });

    test('a repeated submission replays instead of creating a second grade', async ({
        browser,
        watch,
    }) => {
        const page = await graderPage(browser, f, watch);
        const rows = [
            {
                exam_enrollment_id: f.seats.replay,
                score: '61',
                is_absent: false,
            },
        ];
        const key = `e2e-grade-sheet-replay-${f.tag}`;

        expect(
            (await postSheet(page, f, f.sessions.open, rows, key)).outcomes[
                f.seats.replay
            ],
        ).toBe('saved');
        expect(
            (await postSheet(page, f, f.sessions.open, rows, key)).outcomes[
                f.seats.replay
            ],
        ).toBe('replayed');
        expect(gradeRows(f.seats.replay)).toHaveLength(1);
    });

    test('tampered seat and session ids are refused row by row', async ({
        browser,
        watch,
    }) => {
        const page = await graderPage(browser, f, watch);
        const sameSession = await postSheet(page, f, f.sessions.open, [
            {
                exam_enrollment_id: f.seats.other_section,
                score: '50',
                is_absent: false,
            }, // same session, another section
            {
                exam_enrollment_id: f.seats.uncovered,
                score: '50',
                is_absent: false,
            }, // a seat of another session
            {
                exam_enrollment_id: 2_000_000_000,
                score: '50',
                is_absent: false,
            }, // no such seat
        ]);
        expect(sameSession.outcomes).toEqual({
            [f.seats.other_section]: 'grades.sheet.seat_forbidden',
            [f.seats.uncovered]: 'grades.sheet.seat_not_in_session',
            2000000000: 'grades.sheet.seat_not_in_session',
        });
        const otherSession = await postSheet(page, f, f.sessions.uncovered, [
            {
                exam_enrollment_id: f.seats.uncovered,
                score: '50',
                is_absent: false,
            },
        ]);
        expect(otherSession.outcomes[f.seats.uncovered]).toBe(
            'grades.sheet.seat_forbidden',
        );

        expect(gradeRows(f.seats.other_section)).toHaveLength(0);
        expect(gradeRows(f.seats.uncovered)).toHaveLength(0);
        expect(
            Number(
                dbValue(
                    "SELECT count(*) FROM security.security_audit_logs WHERE action = 'grades.enter.scope' AND target_id = ?",
                    [String(f.seats.other_section)],
                ),
            ),
        ).toBeGreaterThan(0);
    });

    test('a session that is not open shows entry closed and the server refuses it', async ({
        browser,
        watch,
    }) => {
        const page = await graderPage(browser, f, watch);
        await page.goto(sheetUrl(f, f.sessions.closed));
        await expect(
            page.locator('[data-window-id="grades.sheet"]'),
        ).toContainText('الجلسة لا تسمح بإدخال الدرجات الآن');
        await expect(
            page.locator('tbody input[type="number"]').first(),
        ).toBeDisabled();

        const result = await postSheet(page, f, f.sessions.closed, [
            {
                exam_enrollment_id: f.seats.closed,
                score: '50',
                is_absent: false,
            },
        ]);
        expect(result.outcomes[f.seats.closed]).toBe(
            'grades.session_unavailable',
        );
        expect(gradeRows(f.seats.closed)).toHaveLength(0);
    });

    test('the manager sees every active seat of the session', async ({
        pageAs,
    }) => {
        const page = await pageAs('admin');
        await page.goto(sheetUrl(f, f.sessions.open));
        const props = (await inertiaPage<SheetProps>(page)).props;

        expect(props.scope.school_wide).toBe(true);
        expect(props.rows.map((r) => r.exam_enrollment_id)).toEqual(
            expect.arrayContaining([
                f.seats.other_section,
                f.seats.other_section_2,
                f.seats.graded,
            ]),
        );
        expect(props.rows).toHaveLength(6);
        await expect(
            page.locator('[data-window-id="grades.sheet"] dl'),
        ).toContainText('كل طلاب الجلسة');
    });

    for (const persona of ['viewer', 'teacher'] as const) {
        test(`a user without grade entry (${persona}) can neither open nor save the sheet`, async ({
            pageAs,
            watch,
        }) => {
            watch.allow(/\/grades\/sheet/, 403);
            const page = await pageAs(persona);
            expect(
                (await page.goto(sheetUrl(f, f.sessions.open)))?.status(),
            ).toBe(403);
            const response = await send(
                page,
                'POST',
                `/grades/sheet/${f.sessions.open}`,
                {
                    rows: [
                        {
                            exam_enrollment_id: f.seats.other_section_2,
                            score: '50',
                            is_absent: false,
                        },
                    ],
                },
            );
            expect(response.status()).toBe(403);
            expect(gradeRows(f.seats.other_section_2)).toHaveLength(0);
        });
    }
});
