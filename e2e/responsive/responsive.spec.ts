/**
 * @responsive — the daily pages on the desktop, tablet (820 px) and phone (Pixel 7) projects: they open without errors,
 * the page itself never scrolls sideways (wide tables scroll inside their own box), the navigation can be reached
 * and the page's main content is visible.
 */
import { dbValue } from '../support/db';
import { expect, test } from '../support/test';

const PAGES: [string, string | (() => string)][] = [
    ['students', '/students'],
    ['teachers', '/teachers'],
    ['enrollments', '/enrollments'],
    ['rooms', '/organization/rooms'],
    ['timetable', '/timetable'],
    ['attendance review', '/attendance/review'],
    ['attendance sheet', () => `/attendance/sheets/${dbValue('SELECT id FROM attendance.sessions WHERE school_id = 1 AND sheet_status = 2 ORDER BY id DESC LIMIT 1')}`],
    ['staff day', '/attendance/staff'],
    ['leave requests', '/leave/requests'],
    ['holidays', '/leave/holidays'],
];

for (const [label, target] of PAGES) {
    test(`@responsive ${label}`, async ({ page }) => {
        const url = typeof target === 'string' ? target : target();
        const response = await page.goto(url);
        expect(response?.status()).toBeLessThan(400);
        await page.waitForLoadState('networkidle');

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow, 'the page itself scrolls sideways (px)').toBeLessThanOrEqual(1);
        await expect(page.getByRole('button', { name: /Toggle sidebar|القائمة|الشريط الجانبي/ }).first()).toBeVisible();
        await expect(page.locator('main, [role=main]').first()).toBeVisible();
    });
}

test('@responsive the teacher marks their day on a phone', async ({ pageAs }) => {
    const page = await pageAs('teacher');
    const day = dbValue<string>('SELECT max(session_date)::text FROM attendance.sessions WHERE school_id = 1');
    await page.goto(`/attendance/my?date=${day}`);
    await page.waitForLoadState('networkidle');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
    await expect(page.locator('main, [role=main]').first()).toBeVisible();
});
