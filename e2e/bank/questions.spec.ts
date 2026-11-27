/**
 * EXAMIS 7.3a — «بنك الأسئلة» through the real browser and server: the admin persona writes a single-choice
 * question from the «تحرير» ribbon, edits the draft and sends it for review; as its author it is never offered
 * «اعتماد» (four eyes); a second approver of the school approves it; a viewer cannot open the bank; the list and a
 * question page in ar / en without page-level horizontal scroll (@responsive). Also: server search and status filter,
 * the empty state, a refused save shown as a message, and the English labels (closure check 2026-10-27).
 *
 * Needs `php e2e_u01_fixture.php bank-approver <db> <school>` (lab script) on a disposable sis_e2e_examis_* copy:
 * it writes storage/app/e2e/bank-fixture.json (approver account + one approved sample question).
 */
import fs from 'node:fs';
import path from 'node:path';
import type { Page, TestInfo } from '@playwright/test';
import { dbValue } from '../support/db';
import { BASE_URL, E2E_DB, RUN, authFile, personaPassword } from '../support/env';
import { command } from '../support/examis';
import { expect, test } from '../support/test';
import { choose, expectNotice, field, openDialog } from '../support/ui';

type Fixture = { database: string; school_id: number; approver_email: string; sample_question_id: number };

const MANIFEST = path.join('storage', 'app', 'e2e', 'bank-fixture.json');

function fixture(): Fixture {
    const f = JSON.parse(fs.readFileSync(MANIFEST, 'utf8')) as Fixture;
    expect(f.database, `${MANIFEST} belongs to another database`).toBe(E2E_DB);

    return f;
}

async function signIn(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(personaPassword());
    await page.locator('[data-test="login-button"]').click();
    await expect(page).not.toHaveURL(/\/login(\?|$)/, { timeout: 30_000 });
}

async function noPageScroll(page: Page, url: string, info: TestInfo): Promise<void> {
    const widths = info.project.name === 'chromium' ? [1366, 820, 390] : [null];
    for (const [lang, dir] of [['ar', 'rtl'], ['en', 'ltr']] as const) {
        await page.context().addCookies([{ name: 'locale', value: lang, url: BASE_URL }]);
        for (const width of widths) {
            if (width !== null) {
                await page.setViewportSize({ width, height: 900 });
            }
            await page.goto(url);
            await expect(page.locator('html')).toHaveAttribute('dir', dir);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow, `horizontal page scroll (${lang}, ${width ?? info.project.name}) on ${url}`).toBeLessThanOrEqual(1);
        }
    }
    await page.context().addCookies([{ name: 'locale', value: 'ar', url: BASE_URL }]);
}

test.describe('question bank (7.3a)', () => {
    test.describe.configure({ mode: 'serial' });
    test.skip(E2E_DB === 'sis_e2e', 'the approver fixture is only made on a disposable sis_e2e_examis_* copy');

    const stem = `ما عاصمة اليمن؟ ${RUN}`;
    const questionId = () => Number(dbValue('SELECT question_id FROM bank.question_versions WHERE stem LIKE ? ORDER BY id DESC LIMIT 1', [`%${RUN}%`]));
    const status = () => dbValue<string>('SELECT status FROM bank.question_versions WHERE question_id = ? ORDER BY version_no DESC LIMIT 1', [questionId()]);

    test('the author writes, edits and submits a question and is never offered its approval', async ({ page }) => {
        fixture();
        await page.goto('/bank/questions');
        await command(page, 'سؤال جديد');
        const create = openDialog(page, 'سؤال جديد');
        await choose(page, create, 'المادة', /./);
        await field(create, 'نص السؤال').fill(stem);
        await field(create, 'الدرجة').fill('2');
        await create.getByRole('textbox', { name: 'الخيار A' }).fill('عدن');
        await create.getByRole('textbox', { name: 'الخيار B' }).fill('صنعاء');
        await create.getByRole('radio', { name: 'صحيح B' }).check();
        await create.getByRole('button', { name: 'إنشاء', exact: true }).click();
        await expect(page).toHaveURL(/\/bank\/questions\/\d+$/);
        expect(page.url()).toContain(`/bank/questions/${questionId()}`);
        expect(status()).toBe('draft');
        // The author sees the answer key.
        await expect(page.getByRole('list', { name: 'الخيارات' }).getByRole('listitem').filter({ hasText: 'صنعاء' })).toContainText('الإجابة الصحيحة');

        await command(page, 'تعديل المسودة');
        const edit = openDialog(page, 'تعديل المسودة');
        await field(edit, 'الدرجة').fill('3');
        await edit.getByRole('button', { name: 'حفظ', exact: true }).click();
        await expect.poll(() => dbValue('SELECT max_mark FROM bank.question_versions WHERE question_id = ?', [questionId()])).toBe('3.00');

        await command(page, 'إرسال للمراجعة');
        await page.getByRole('dialog').getByRole('button', { name: 'إرسال للمراجعة' }).click();
        await expect.poll(status).toBe('in_review');
        // The admin persona is also an approver: the page says why it is not offered «اعتماد» (four eyes).
        await expect(page.getByRole('status').filter({ hasText: 'لا يجوز لكاتب النسخة أن يعتمدها' })).toBeVisible();
        await expect(page.getByRole('button', { name: /^اعتماد( — .*)?$/ })).toHaveCount(0);
        await expect(page.getByRole('button', { name: /^تعديل المسودة( — .*)?$/ })).toHaveCount(0);
    });

    test('a second approver of the school approves it', async ({ browser }, info) => {
        const f = fixture();
        const context = await browser.newContext({ ...info.project.use, storageState: { cookies: [], origins: [] }, baseURL: BASE_URL, locale: 'ar-IQ' });
        const page = await context.newPage();
        await signIn(page, f.approver_email);
        await page.goto(`/bank/questions/${questionId()}`);
        await command(page, 'اعتماد');
        const approve = openDialog(page, 'اعتماد');
        await approve.getByRole('button', { name: 'اعتماد', exact: true }).click();
        await expect.poll(status).toBe('approved');
        expect(Number(dbValue('SELECT decided_by FROM bank.question_versions WHERE question_id = ?', [questionId()]))).toBe(
            Number(dbValue('SELECT id FROM users WHERE email = ?', [f.approver_email])),
        );
        await context.close();
    });

    test('search, status filter, empty state, a refused save and the English page', async ({ page }) => {
        // No results (a filter is on) is told apart from an empty bank, and «مسح التصفية» brings the whole list back.
        await page.goto(`/bank/questions?search=${encodeURIComponent(`لا-يوجد-${RUN}`)}`);
        await expect(page.getByText('لا نتائج', { exact: true }).first()).toBeVisible();
        await expect(page.getByText('عدد الأسئلة: 0')).toBeVisible();
        await page.getByRole('button', { name: 'مسح التصفية' }).click();
        await expect(page).toHaveURL(/\/bank\/questions$/);
        await expect(page.getByRole('button', { name: 'مسح التصفية' })).toHaveCount(0);
        await expect(page.locator('tbody tr', { hasText: RUN }).first()).toBeVisible();

        // Search through the form (server side) finds the question written above; the status filter narrows it.
        await page.goto('/bank/questions');
        await page.getByRole('searchbox', { name: 'بحث في نص السؤال' }).fill(RUN);
        await page.getByRole('button', { name: 'بحث', exact: true }).click();
        await expect(page).toHaveURL(new RegExp(`search=${RUN}`));
        await expect(page.locator('tbody tr', { hasText: RUN })).toHaveCount(1);
        await page.goto(`/bank/questions?search=${RUN}&status=approved`);
        await expect(page.locator('tbody tr', { hasText: RUN })).toHaveCount(1);
        await page.goto(`/bank/questions?search=${RUN}&status=draft`);
        await expect(page.locator('tbody tr', { hasText: RUN })).toHaveCount(0);

        // A single-choice question without its correct option is refused by the server with a readable message.
        const before = Number(dbValue('SELECT count(*) FROM bank.questions'));
        await page.goto('/bank/questions');
        await command(page, 'سؤال جديد');
        const create = openDialog(page, 'سؤال جديد');
        await choose(page, create, 'المادة', /./);
        await field(create, 'نص السؤال').fill(`سؤال بلا إجابة ${RUN}`);
        await create.getByRole('textbox', { name: 'الخيار A' }).fill('أ');
        await create.getByRole('textbox', { name: 'الخيار B' }).fill('ب');
        await create.getByRole('button', { name: 'إنشاء', exact: true }).click();
        await expectNotice(page, /محتوى السؤال غير صالح/);
        expect(Number(dbValue('SELECT count(*) FROM bank.questions'))).toBe(before);

        // English: labels from en.ts, left-to-right.
        await page.context().addCookies([{ name: 'locale', value: 'en', url: BASE_URL }]);
        await page.goto('/bank/questions');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Question bank');
        await page.goto(`/bank/questions/${questionId()}`);
        await expect(page.getByText('correct answer')).toBeVisible();
        await page.context().addCookies([{ name: 'locale', value: 'ar', url: BASE_URL }]);
    });

    test('list → question → back, and times in the display zone whatever the browser zone', async ({ browser }, info) => {
        // A browser in UTC: the page must still show Asia/Baghdad time (the zone the server sends), not the browser's.
        const context = await browser.newContext({ ...info.project.use, storageState: authFile('admin'), baseURL: BASE_URL, locale: 'ar-IQ', timezoneId: 'UTC' });
        const page = await context.newPage();
        await page.goto(`/bank/questions?search=${RUN}`);
        await page.locator('tbody tr', { hasText: RUN }).getByRole('link', { name: 'عرض' }).click();
        await expect(page).toHaveURL(new RegExp(`/bank/questions/${questionId()}$`));
        await expect(page.getByText('الأوقات بتوقيت Asia/Baghdad')).toBeVisible();

        const submitted = dbValue<string>('SELECT submitted_at FROM bank.question_versions WHERE question_id = ? ORDER BY version_no DESC LIMIT 1', [questionId()]);
        const expected = new Intl.DateTimeFormat('ar', {
            timeZone: 'Asia/Baghdad', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false, numberingSystem: 'latn',
        }).format(new Date(submitted.replace(' ', 'T').replace(/\+00$/, '+00:00')));
        await expect(page.getByRole('list', { name: 'سجل الحالة' }).first()).toContainText(expected);

        await page.getByRole('link', { name: 'العودة للقائمة' }).click();
        await expect(page).toHaveURL(/\/bank\/questions$/);
        await context.close();
    });

    test('a viewer cannot open the bank', async ({ pageAs, watch }) => {
        watch.allow(/\/bank\/questions/, 403);
        const page = await pageAs('viewer');
        const response = await page.goto('/bank/questions');
        expect(response?.status()).toBe(403);
    });

    test('@responsive the list and a question page render without page-level horizontal scroll', async ({ page }, info) => {
        const f = fixture();
        await noPageScroll(page, '/bank/questions', info);
        await noPageScroll(page, `/bank/questions/${f.sample_question_id}`, info);
    });
});
