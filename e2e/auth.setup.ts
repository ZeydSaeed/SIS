import { expect, test as setup } from '@playwright/test';
import { dbValue } from './support/db';
import { authFile, E2E_DB, PERSONAS, personaPassword, type Persona } from './support/env';

/**
 * Runs before every project: proves the server and the helper point at the e2e dataset (never the live
 * database), then signs every persona in through the real login form and keeps its session.
 */
setup('the e2e dataset is in place', () => {
    expect(dbValue<string>('SELECT current_database()')).toBe(E2E_DB);
    // The seeded batch (tests add their own records, tagged with the run, on top of it).
    expect(Number(dbValue("SELECT count(*) FROM students.students WHERE full_name NOT LIKE '%اختبار%'"))).toBe(200);
    expect(Number(dbValue("SELECT count(*) FROM teachers.teachers WHERE employee_code ~ '^T-[SE][0-9]{3}$'"))).toBe(100);
    expect(Number(dbValue("SELECT count(*) FROM public.users WHERE email LIKE 'e2e-%@sis.test'"))).toBe(Object.keys(PERSONAS).length);
});

for (const persona of Object.keys(PERSONAS) as Persona[]) {
    setup(`sign in: ${persona}`, async ({ page }) => {
        await page.goto('/login');
        await page.locator('input[name="email"]').fill(PERSONAS[persona]);
        await page.locator('input[name="password"]').fill(personaPassword());
        await page.locator('[data-test="login-button"]').click();
        // One single-threaded PHP server: the first sign-in (password hashing, cold caches) can take a while.
        await expect(page).not.toHaveURL(/\/login(\?|$)/, { timeout: 30_000 });
        await page.context().storageState({ path: authFile(persona) });
    });
}
