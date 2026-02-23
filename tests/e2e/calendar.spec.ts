import { test, expect } from '@playwright/test';

test.describe('Calendar Page', () => {

  test('displays month heading and navigation controls', async ({ page }) => {
    await page.goto('/activities?year=2025&month=11');
    const heading = page.locator('h2');
    await expect(heading).toContainText('November 2025');

    await expect(page.locator('a[aria-label="Previous month"]')).toBeVisible();
    await expect(page.locator('a[aria-label="Next month"]')).toBeVisible();
  });

  test('navigates to previous month', async ({ page }) => {
    await page.goto('/activities?year=2025&month=11');
    await page.locator('a[aria-label="Previous month"]').click();
    await expect(page.locator('h2')).toContainText('October 2025');
  });

  test('navigates to next month', async ({ page }) => {
    await page.goto('/activities?year=2025&month=11');
    await page.locator('a[aria-label="Next month"]').click();
    await expect(page.locator('h2')).toContainText('December 2025');
  });

  test('disables next month button on current month', async ({ page }) => {
    await page.goto('/activities?year=2026&month=2');
    await expect(page.locator('span[aria-disabled="true"]')).toBeVisible();
    await expect(page.locator('a[aria-label="Next month"]')).toHaveCount(0);
  });

  test('enables next month on past months', async ({ page }) => {
    await page.goto('/activities?year=2025&month=10');
    await expect(page.locator('a[aria-label="Next month"]')).toBeVisible();
    await expect(page.locator('span[aria-disabled="true"]')).toHaveCount(0);
  });

  test('clamps future month to current month', async ({ page }) => {
    await page.goto('/activities?year=2026&month=6');
    await expect(page.locator('h2')).toContainText('February 2026');
  });

  test('displays activity dots for months with data', async ({ page }) => {
    await page.goto('/activities?year=2025&month=10');
    const dots = page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]');
    const count = await dots.count();
    expect(count).toBeGreaterThan(0);
  });

  test('activity dots show distance labels', async ({ page }) => {
    await page.goto('/activities?year=2025&month=10');
    const firstDot = page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]').first();
    const text = await firstDot.textContent();
    expect(text?.trim()).toMatch(/\d+\.\d/);
  });

  test('filters by pattern', async ({ page }) => {
    await page.goto('/activities?year=2025&month=10');
    const allDots = page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]');
    const totalBefore = await allDots.count();

    // Select pattern filter — form auto-submits via onchange
    await page.selectOption('#filter-pattern', 'easy 9km');

    // Wait for the calendar grid Turbo Frame to update by checking the heading is still October
    // and the selected option is now "easy 9km"
    await expect(page.locator('#filter-pattern')).toHaveValue('easy 9km');
    // Wait for the grid to stabilize
    await page.waitForLoadState('networkidle');

    const filteredDots = page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]');
    const totalAfter = await filteredDots.count();

    expect(totalAfter).toBeLessThan(totalBefore);
    expect(totalAfter).toBeGreaterThan(0);

    // Clear filters link should appear
    await expect(page.getByText('Clear filters')).toBeVisible();

    // URL should reflect the applied filter
    expect(page.url()).toContain('pattern=easy');
  });

  test('filters by gear', async ({ page }) => {
    await page.goto('/activities?year=2025&month=10');
    const allDots = page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]');
    const totalBefore = await allDots.count();

    await page.selectOption('#filter-gear', 'Brooks Ghost 15');
    await expect(page.locator('#filter-gear')).toHaveValue('Brooks Ghost 15');
    await page.waitForLoadState('networkidle');

    const filteredDots = page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]');
    const totalAfter = await filteredDots.count();

    expect(totalAfter).toBeLessThan(totalBefore);
    expect(totalAfter).toBeGreaterThan(0);

    await expect(page.getByText('Clear filters')).toBeVisible();

    // URL should reflect the applied gear filter
    expect(page.url()).toContain('gear=Brooks');
  });

  test('clears filters', async ({ page }) => {
    // Start with a filter applied via URL params
    await page.goto('/activities?year=2025&month=10&pattern=easy+9km');
    await expect(page.getByText('Clear filters')).toBeVisible();

    const filteredCount = await page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]').count();

    // Click clear filters — this navigates within the Turbo Frame
    await page.getByText('Clear filters').click();
    // Wait for network to settle and "Clear filters" to disappear
    await expect(page.getByText('Clear filters')).toHaveCount(0, { timeout: 10000 });

    const clearedCount = await page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]').count();
    expect(clearedCount).toBeGreaterThanOrEqual(filteredCount);

    // URL should no longer contain filter query parameters
    const url = new URL(page.url());
    expect(url.searchParams.has('pattern')).toBe(false);
    expect(url.searchParams.has('gear')).toBe(false);
  });

  test('loads activity card in sidebar when clicking a dot', async ({ page }) => {
    await page.goto('/activities?year=2025&month=10');

    const sidebar = page.locator('turbo-frame#activity-detail');
    await expect(sidebar).toContainText('Select an activity to view details');

    const firstDot = page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]').first();
    await firstDot.click();

    // Wait for sidebar to load activity card
    await expect(sidebar.locator('h3')).toBeVisible({ timeout: 10000 });
    await expect(sidebar.locator('dt:has-text("Distance")')).toBeVisible();
    await expect(sidebar.locator('dt:has-text("Pace")')).toBeVisible();
  });

  test('updates sidebar when clicking different activity', async ({ page }) => {
    await page.goto('/activities?year=2025&month=10');

    const dots = page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]');
    const sidebar = page.locator('turbo-frame#activity-detail');

    // Click first dot
    await dots.first().click();
    await expect(sidebar.locator('h3')).toBeVisible({ timeout: 10000 });
    const firstName = await sidebar.locator('h3').textContent();

    // Click last dot
    await dots.last().click();

    // Wait for sidebar to update with different content
    await expect(sidebar.locator('h3')).not.toHaveText(firstName!, { timeout: 10000 });
  });

  test('sidebar card shows View Activity link', async ({ page }) => {
    await page.goto('/activities?year=2025&month=10');

    const firstDot = page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]').first();
    await firstDot.click();

    const sidebar = page.locator('turbo-frame#activity-detail');
    await expect(sidebar.locator('h3')).toBeVisible({ timeout: 10000 });
    await expect(sidebar.getByText('View Activity')).toBeVisible();
  });

  test('shows empty state for month without activities', async ({ page }) => {
    await page.goto('/activities?year=2025&month=9');
    const dots = page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]');
    await expect(dots).toHaveCount(0);
  });

});
