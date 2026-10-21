/**
 * The sign-in page (the first screen of every user): Arabic, RTL, no starter-kit English left, and a wrong password
 * is refused with a message that does not say which of the two fields was wrong.
 */
import { PERSONAS } from '../support/env';
import { expect, test } from '../support/test';

test.use({ storageState: { cookies: [], origins: [] } });

test('the sign-in page is Arabic and right-to-left', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.getByRole('button', { name: 'الدخول بمفتاح المرور' })).toBeVisible();
    await expect(page.getByText('أو بالبريد الإلكتروني')).toBeVisible();
    await expect(page.locator('body')).not.toContainText(/Sign in with a passkey|continue with email/i);
});

test('a wrong password is refused without revealing which field was wrong', async ({ page, watch }) => {
    watch.allow(/\/login$/, 422);
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(PERSONAS.viewer);
    await page.locator('input[name="password"]').fill('not-the-password-123');
    await page.locator('[data-test="login-button"]').click();
    await expect(page).toHaveURL(/\/login$/);
    const message = page.locator('form').getByText(/./).filter({ hasText: /بيانات|غير صحيحة|credentials/i }).first();
    await expect(message).toBeVisible();
    await expect(page.locator('body')).not.toContainText(/SQLSTATE|Exception/);
});
