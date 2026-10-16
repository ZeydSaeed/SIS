import { test, expect } from '@playwright/test';

test('تفتح الصفحة الرئيسية بنجاح', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000');

  await expect(page).toHaveURL(/127\.0\.0\.1:8000/);
});
