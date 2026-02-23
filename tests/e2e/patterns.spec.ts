import { test, expect } from '@playwright/test';

test.describe('Pattern List Page', () => {

  test('displays page heading', async ({ page }) => {
    await page.goto('/activities/pattern');
    await expect(page.locator('h1')).toContainText('Activity Patterns');
  });

  test('displays all pattern groups', async ({ page }) => {
    await page.goto('/activities/pattern');
    const groups = page.locator('.bg-gray-900.rounded-lg');
    const count = await groups.count();
    expect(count).toBeGreaterThanOrEqual(5);
  });

  test('shows activity counts per group', async ({ page }) => {
    await page.goto('/activities/pattern');
    const activityBadges = page.locator('text=/\\d+ activit/');
    const count = await activityBadges.count();
    expect(count).toBeGreaterThan(0);

    // Verify "easy 9km" group exists and shows activity count
    const easy9kmGroup = page.locator('.bg-gray-900.rounded-lg', {
      has: page.locator('a:has-text("easy 9km")'),
    });
    await expect(easy9kmGroup.locator('text=/\\d+ activities/')).toBeVisible();
  });

  test('shows activity data in tables', async ({ page }) => {
    await page.goto('/activities/pattern');
    const rows = page.locator('[data-sortable-table-target="body"] tr');
    const count = await rows.count();
    expect(count).toBeGreaterThan(0);

    const firstRow = rows.first();
    await expect(firstRow.locator('td').nth(0)).not.toBeEmpty();
    await expect(firstRow.locator('td').nth(1)).not.toBeEmpty();
  });

  test('client-side sorts by column', async ({ page }) => {
    await page.goto('/activities/pattern');

    const firstTable = page.locator('[data-controller="sortable-table"]').first();
    const tbody = firstTable.locator('[data-sortable-table-target="body"]');
    const rows = tbody.locator('tr');

    const rowCount = await rows.count();
    if (rowCount <= 1) {
      // Skip if only one row — can't verify sorting
      return;
    }

    // Click the "Distance" header to sort
    const distanceHeader = firstTable.locator('[data-col="2"]');
    await distanceHeader.click();

    // Get sorted values
    const sortedValues: number[] = [];
    for (let i = 0; i < rowCount; i++) {
      const text = await rows.nth(i).locator('td').nth(2).textContent();
      sortedValues.push(parseFloat(text?.replace(' km', '') ?? '0'));
    }

    // Verify ascending order
    for (let i = 1; i < sortedValues.length; i++) {
      expect(sortedValues[i]).toBeGreaterThanOrEqual(sortedValues[i - 1]);
    }
  });

  test('shows unclassified activities group', async ({ page }) => {
    await page.goto('/activities/pattern');
    await expect(page.locator('span.text-gray-400:has-text("Unclassified")')).toBeVisible();
  });

  test('navigates to pattern detail on signature click', async ({ page }) => {
    await page.goto('/activities/pattern');
    const patternLink = page.locator('a.text-strava-orange').first();
    const signatureText = await patternLink.textContent();
    await patternLink.click();

    await expect(page).toHaveURL(/\/activities\/pattern\//);
    await expect(page.locator('h1')).toContainText(signatureText!.trim());
  });

  test('activity names link to detail page', async ({ page }) => {
    await page.goto('/activities/pattern');
    const activityLink = page.locator('[data-sortable-table-target="body"] a').first();
    const href = await activityLink.getAttribute('href');
    expect(href).toMatch(/\/activities\/\d+\/detail/);
  });

});

test.describe('Pattern Detail Page', () => {

  test('displays pattern heading and activity count', async ({ page }) => {
    await page.goto('/activities/pattern/easy%209km');
    await expect(page.locator('h1')).toContainText('easy 9km');
    await expect(page.locator('text=/\\d+ activities/')).toBeVisible();
  });

  test('shows "All patterns" back link', async ({ page }) => {
    await page.goto('/activities/pattern/easy%209km');
    const backLink = page.getByText('All patterns');
    await expect(backLink).toBeVisible();
    await backLink.click();
    await expect(page).toHaveURL(/\/activities\/pattern$/);
  });

  test('displays activities in a table', async ({ page }) => {
    await page.goto('/activities/pattern/easy%209km');
    const rows = page.locator('turbo-frame#pattern-table tbody tr');
    const count = await rows.count();
    expect(count).toBeGreaterThan(0);
  });

  test('shows trend text', async ({ page }) => {
    await page.goto('/activities/pattern/easy%209km');
    await expect(page.locator('text=/Trend:/')).toBeVisible();
  });

  test('sorts by column via server-side Turbo Frame', async ({ page }) => {
    await page.goto('/activities/pattern/easy%209km');

    // Click the "Distance" sort link inside the Turbo Frame
    const distanceSort = page.locator('turbo-frame#pattern-table a:has-text("Distance")');
    await distanceSort.click();

    // Wait for the sort indicator to appear (Turbo Frame reloads)
    await expect(
      page.locator('turbo-frame#pattern-table span.text-strava-orange')
    ).toBeVisible({ timeout: 10000 });

    // Verify sort indicator arrow is shown next to Distance
    const distanceHeader = page.locator('turbo-frame#pattern-table th', {
      has: page.locator('a:has-text("Distance")'),
    });
    await expect(distanceHeader.locator('.text-strava-orange')).toBeVisible();
  });

  test('comparison checkboxes and button work', async ({ page }) => {
    await page.goto('/activities/pattern/easy%209km');

    const compareBtn = page.locator('[data-comparison-selector-target="compareButton"]');
    await expect(compareBtn).toBeDisabled();
    await expect(compareBtn).toContainText('Compare (0)');

    const checkboxes = page.locator('[data-comparison-selector-target="checkbox"]');
    await checkboxes.nth(0).check();
    await checkboxes.nth(1).check();

    await expect(compareBtn).toBeEnabled();
    await expect(compareBtn).toContainText('Compare (2)');
  });

  test('activity name links to detail page', async ({ page }) => {
    await page.goto('/activities/pattern/easy%209km');
    const activityLink = page.locator('turbo-frame#pattern-table tbody a').first();
    const href = await activityLink.getAttribute('href');
    expect(href).toMatch(/\/activities\/\d+\/detail/);
  });

});
