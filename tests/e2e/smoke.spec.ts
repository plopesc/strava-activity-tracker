import { test, expect } from '@playwright/test';

test('homepage redirects to calendar', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveURL(/\/activities/);
});

test('calendar page loads', async ({ page }) => {
  await page.goto('/activities');
  await expect(page.locator('h2')).toBeVisible();
});
