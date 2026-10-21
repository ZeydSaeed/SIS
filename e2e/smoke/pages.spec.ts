/**
 * Every page of the application (discovered from `route:list`, so a new page is covered automatically) opens for
 * the administrator without a JavaScript error, console error or HTTP failure, in Arabic RTL, with a title and no
 * horizontal page scroll. Its fields, buttons and tables are recorded in storage/app/e2e/inventory for the
 * coverage register (docs/testing/SIS-DATA-AND-UI-COVERAGE.md). Pages whose parameters have no data in the e2e
 * dataset are skipped with the reason — never silently.
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { dbValue } from '../support/db';
import { phpEnv } from '../support/env';
import { expect, inertiaPage, test } from '../support/test';

type Route = { uri: string; name: string | null; method: string };

/** Not pages: files, redirects, auth screens of signed-out users, the welcome page. */
const NOT_A_PAGE = [
    /^api\//, /^_/, /^sanctum/, /^storage/, /^\.well-known/, /^up$/, /^\/?$/,
    /export$/, /download$/, /^imports\/templates/, /attachments\/\{document\}$/,
    /^(login|register|forgot-password|reset-password|two-factor-challenge|email|user|confirm-password|logout|passkeys?)/,
    /^settings$/, /^settings\/(password|two-factor)$/, /^dashboard$/,
];

const routes: Route[] = (JSON.parse(
    execFileSync('php', ['artisan', 'route:list', '--method=GET', '--json', '--except-vendor'], { env: phpEnv(), encoding: 'utf8' }),
) as Route[]).filter((r) => r.method.includes('GET') && !NOT_A_PAGE.some((p) => p.test(r.uri)));

/** A record of the dataset for each route parameter — null when the dataset has none (the page is skipped). */
const PARAMS: Record<string, () => string | null> = {
    student: () => dbValue("SELECT s.id FROM students.students s JOIN enrollment.enrollments e ON e.student_id = s.id AND e.status = 1 ORDER BY s.id LIMIT 1"),
    teacher: () => dbValue("SELECT id FROM teachers.teachers WHERE employee_code = 'T-S001'"),
    enrollment: () => dbValue('SELECT id FROM enrollment.enrollments WHERE status = 1 ORDER BY id LIMIT 1'),
    curriculum: () => dbValue('SELECT id FROM curriculum.curricula WHERE status = 1 AND school_id = 1 ORDER BY id LIMIT 1'),
    application: () => dbValue('SELECT id FROM admission.applications WHERE school_id = 1 ORDER BY id LIMIT 1'),
    session: () => dbValue('SELECT id FROM attendance.sessions WHERE school_id = 1 AND sheet_status = 4 ORDER BY id LIMIT 1'),
    schedule: () => dbValue('SELECT id FROM timetable.schedules WHERE school_id = 1 AND lifecycle_status = 1 ORDER BY id LIMIT 1'),
    exam: () => dbValue('SELECT id FROM exams.exams ORDER BY id LIMIT 1'),
    recommendation: () => dbValue('SELECT id FROM intelligence.recommendations ORDER BY id LIMIT 1'),
    batch: () => dbValue('SELECT id FROM imports.batches ORDER BY id LIMIT 1'),
};

function resolve(uri: string): { url: string } | { skip: string } {
    let url = uri;
    for (const [, param] of uri.matchAll(/\{(\w+)\??\}/g)) {
        let value: string | null = null;
        try {
            value = PARAMS[param]?.() ?? null;
        } catch {
            value = null;
        }
        if (value === null || value === undefined) {
            return { skip: `no «${param}» record in the e2e dataset` };
        }
        url = url.replace(new RegExp(`\\{${param}\\??\\}`), String(value));
    }

    return { url: '/' + url };
}

const inventoryDir = path.join('storage', 'app', 'e2e', 'inventory');

test.describe('every page opens cleanly (admin)', () => {
    for (const route of routes) {
        test(`${route.uri}`, async ({ page }) => {
            const target = resolve(route.uri);
            test.skip('skip' in target, 'skip' in target ? target.skip : '');
            if (!('url' in target)) {
                return;
            }

            const response = await page.goto(target.url);
            expect(response?.status(), `HTTP status of ${target.url}`).toBeLessThan(400);
            await page.waitForLoadState('networkidle');

            await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
            await expect(page.locator('html')).toHaveAttribute('lang', /^ar/);
            await expect(page).toHaveTitle(/\S/);
            const inertia = await inertiaPage(page);
            expect(inertia.component, 'Inertia component').toBeTruthy();

            const inventory = await page.evaluate(() => {
                const visible = (el: Element) => !!((el as HTMLElement).offsetWidth || (el as HTMLElement).offsetHeight || el.getClientRects().length);
                const text = (el: Element | null | undefined) => (el?.textContent ?? '').trim().replace(/\s+/g, ' ').slice(0, 60);
                const labelOf = (el: Element) =>
                    el.getAttribute('aria-label') ||
                    (el.id ? text(document.querySelector(`label[for="${CSS.escape(el.id)}"]`)) : '') ||
                    el.getAttribute('placeholder') ||
                    text(el.closest('label')) ||
                    el.getAttribute('name') ||
                    '';
                const fields = [...document.querySelectorAll('input:not([type=hidden]), select, textarea, [role=combobox], [role=checkbox], [role=switch], [role=radio]')]
                    .filter(visible)
                    .map((el) => ({
                        kind: el.getAttribute('role') ?? el.getAttribute('type') ?? el.tagName.toLowerCase(),
                        name: el.getAttribute('name'),
                        label: labelOf(el),
                        required: el.hasAttribute('required') || el.getAttribute('aria-required') === 'true',
                    }));
                const buttons = [...new Set([...document.querySelectorAll('button, [role=button], [role=tab], [role=menuitem]')]
                    .filter(visible)
                    .map((b) => (b.getAttribute('aria-label') || b.getAttribute('title') || text(b)).trim())
                    .filter(Boolean))];
                const tables = [...document.querySelectorAll('table')].filter(visible).map((t) => ({
                    columns: [...t.querySelectorAll('thead th')].map((th) => text(th)).filter(Boolean),
                    rows: t.querySelectorAll('tbody tr').length,
                }));

                return {
                    title: document.title,
                    headings: [...document.querySelectorAll('h1, h2')].filter(visible).map((h) => text(h)).slice(0, 6),
                    fields,
                    buttons,
                    tables,
                    dialogTriggers: [...document.querySelectorAll('[aria-haspopup="dialog"]')].filter(visible).length,
                    overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
                };
            });
            fs.mkdirSync(inventoryDir, { recursive: true });
            fs.writeFileSync(
                path.join(inventoryDir, route.uri.replace(/[{}?]/g, '').replace(/[\\/]+/g, '__') + '.json'),
                JSON.stringify({ uri: route.uri, name: route.name, url: target.url, component: inertia.component, ...inventory }, null, 2),
            );

            expect(inventory.overflowX, 'horizontal page scroll (px)').toBeLessThanOrEqual(1);
            await expect(page.getByText(/Server Error|Whoops|SQLSTATE|Stack trace/i)).toHaveCount(0);
        });
    }
});
