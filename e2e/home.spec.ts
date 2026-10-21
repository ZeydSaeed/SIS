import { test, expect } from '@playwright/test';
import { BASE_URL } from './support/env';

test('تفتح الصفحة الرئيسية بنجاح', async ({ page }) => {
  await page.goto('/');

  expect(page.url().startsWith(new URL(BASE_URL).origin)).toBe(true);
});
