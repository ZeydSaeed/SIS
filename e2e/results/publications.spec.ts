/**
 * EXAMIS U-01 — «نشر النتائج السنوية» through the real browser and server: the page in Arabic (RTL) and English (LTR)
 * at 1366 / 820 / 390 px without page-level horizontal scroll; an incomplete scope cannot be published; a complete
 * section is published after confirmation and the publication is withdrawn with a reason.
 *
 * Needs the U-01 fixture (synthetic official annual results of one section) written by
 * D:\Projects\sis-examis-lab\scripts\e2e_u01_fixture.php to a disposable sis_e2e_examis_* copy; skipped elsewhere.
 */
import fs from 'node:fs';
import path from 'node:path';
import { dbValue } from '../support/db';
import { BASE_URL, E2E_DB, RUN } from '../support/env';
import { expect, test } from '../support/test';
import { expectNotice, openDialog } from '../support/ui';

type Fixture = {
    database: string;
    academic_year_id: number;
    class_id: number;
    section_id: number;
    students: number;
};

const MANIFEST = path.join('storage', 'app', 'e2e', 'u01-fixture.json');

test.describe('result publications (U-01)', () => {
    test.describe.configure({ mode: 'serial' });
    test.skip(
        !E2E_DB.startsWith('sis_e2e_examis_'),
        'needs the U-01 fixture on a sis_e2e_examis_* copy',
    );

    let f: Fixture;
    const url = () =>
        `/results/publications?academic_year_id=${f.academic_year_id}&class_id=${f.class_id}`;
    test.beforeAll(() => {
        f = JSON.parse(fs.readFileSync(MANIFEST, 'utf8')) as Fixture;
        expect(f.database, `${MANIFEST} belongs to another database`).toBe(
            E2E_DB,
        );
    });

    for (const [lang, dir] of [
        ['ar', 'rtl'],
        ['en', 'ltr'],
    ] as const) {
        test(`the page renders in ${lang} (${dir}) at 1366, 820 and 390 px without page scroll`, async ({
            page,
            context,
        }) => {
            await context.addCookies([
                { name: 'locale', value: lang, url: BASE_URL },
            ]);
            for (const width of [1366, 820, 390]) {
                await page.setViewportSize({ width, height: 900 });
                await page.goto(url());
                await expect(page.locator('html')).toHaveAttribute('dir', dir);
                await expect(
                    page.getByRole('heading', { level: 1 }),
                ).toHaveText(
                    lang === 'ar'
                        ? 'نشر النتائج السنوية'
                        : 'Publish annual results',
                );
                const overflow = await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth -
                        document.documentElement.clientWidth,
                );
                expect(
                    overflow,
                    `horizontal page scroll at ${width}px (${lang})`,
                ).toBeLessThanOrEqual(1);
            }
        });
    }

    test('the whole class is not ready: both publish buttons are disabled', async ({
        page,
    }) => {
        await page.goto(url());
        await expect(
            page.getByRole('button', { name: 'نشر النتائج السنوية' }),
        ).toBeDisabled();
        await expect(
            page.getByRole('button', { name: 'نشر معدل السنة' }),
        ).toBeDisabled();
    });

    test('a complete section is published after confirmation, then withdrawn with a reason', async ({
        page,
    }) => {
        const count = (status: string) =>
            Number(
                dbValue(
                    "SELECT count(*) FROM results.result_publications WHERE class_id = ? AND section_id = ? AND kind = 'annual' AND status = ?",
                    [f.class_id, f.section_id, status],
                ),
            );
        const before = count('published');

        await page.goto(`${url()}&section_id=${f.section_id}`);
        await expect(page.getByRole('table')).toBeVisible();
        await page.getByRole('button', { name: 'نشر النتائج السنوية' }).click();
        const confirm = page
            .getByRole('alertdialog')
            .or(page.getByRole('dialog'))
            .filter({ hasText: 'نشر النتائج للطلبة؟' })
            .last();
        await confirm
            .getByRole('button', { name: 'نشر النتائج السنوية' })
            .click();
        await expectNotice(page, 'نُشرت النتائج السنوية للطلبة.');
        await expect.poll(() => count('published')).toBe(before + 1);

        await page.goto(`${url()}&section_id=${f.section_id}`);
        await page
            .getByRole('button', { name: /^سحب النشر: النتائج السنوية/ })
            .first()
            .click();
        const dialog = openDialog(page, 'سحب نشر النتائج');
        const submit = dialog.getByRole('button', {
            name: 'سحب النشر',
            exact: true,
        });
        await expect(submit).toBeDisabled();
        const reason = `اختبار متصفح: سحب بسبب ${RUN}`;
        await dialog.getByRole('textbox').fill(reason);
        await submit.click();
        await expectNotice(page, 'سُحب نشر النتائج.');
        await expect.poll(() => count('published')).toBe(before);
        expect(
            Number(
                dbValue(
                    "SELECT count(*) FROM results.result_publications WHERE class_id = ? AND status = 'withdrawn' AND withdraw_reason = ?",
                    [f.class_id, reason],
                ),
            ),
        ).toBe(1);
    });
});
