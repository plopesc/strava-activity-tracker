import { test, expect, Page } from '@playwright/test';

/**
 * Helper to find an activity ID by navigating to the pattern detail page
 * and searching for a specific activity name. Falls back to calendar search
 * for unclassified activities.
 */
async function findActivityIdByName(page: Page, name: string, patternSignature?: string): Promise<string> {
  if (patternSignature) {
    // Search on the pattern detail page which shows ALL activities
    await page.goto(`/activities/pattern/${encodeURIComponent(patternSignature)}`);
  } else {
    // For unclassified activities, search via the calendar for the specific month
    // Try multiple months where unclassified activities exist in fixtures
    for (const monthParam of ['year=2025&month=10', 'year=2025&month=11', 'year=2025&month=12', 'year=2026&month=1']) {
      await page.goto(`/activities?${monthParam}`);
      // Click on activity dots to find the one matching the name
      const dots = page.locator('.grid.grid-cols-7 a[data-turbo-frame="activity-detail"]');
      const count = await dots.count();
      for (let i = 0; i < count; i++) {
        const title = await dots.nth(i).getAttribute('title');
        if (title?.includes(name)) {
          const href = await dots.nth(i).getAttribute('href');
          const match = href?.match(/\/activities\/(\d+)\/detail/);
          if (match) return match[1];
        }
      }
    }
    throw new Error(`Could not find activity ID for "${name}" in calendar`);
  }

  const link = page.locator(`a[href*="/detail"]:has-text("${name}")`);
  await expect(link).toBeVisible({ timeout: 10000 });
  const href = await link.getAttribute('href');
  const match = href?.match(/\/activities\/(\d+)\/detail/);
  if (!match) {
    throw new Error(`Could not find activity ID for "${name}"`);
  }
  return match[1];
}

test.describe('Activity Detail Page', () => {

  test('shows map, pace chart, and HR chart for full-data activity', async ({ page }) => {
    const id = await findActivityIdByName(page, 'Easy Recovery 9km', 'easy 9km');
    await page.goto(`/activities/${id}/detail`);

    // Map should be present
    await expect(page.locator('#activityMap')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('.leaflet-container')).toBeVisible({ timeout: 10000 });

    // Pace chart canvas should be present
    await expect(page.locator('canvas#paceChart')).toBeVisible();

    // HR chart canvas should be present
    await expect(page.locator('canvas#hrChart')).toBeVisible();

    // "No stream data" message should NOT be visible
    await expect(page.getByText('No stream data available for this activity.')).toHaveCount(0);
  });

  test('Leaflet map initializes with container', async ({ page }) => {
    const id = await findActivityIdByName(page, 'Easy Recovery 9km', 'easy 9km');
    await page.goto(`/activities/${id}/detail`);

    // Leaflet container should exist (map initialized)
    await expect(page.locator('.leaflet-container')).toBeVisible({ timeout: 10000 });
    // Leaflet map pane should exist in the DOM (may not be "visible" per se)
    await expect(page.locator('.leaflet-map-pane')).toBeAttached({ timeout: 10000 });
  });

  test('shows pace chart but NOT HR chart when heartrate is missing', async ({ page }) => {
    const id = await findActivityIdByName(page, 'Easy Weekend 9km', 'easy 9km');
    await page.goto(`/activities/${id}/detail`);

    await expect(page.locator('#activityMap')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('canvas#paceChart')).toBeVisible();
    await expect(page.locator('canvas#hrChart')).toHaveCount(0);
    await expect(page.getByText('No stream data available for this activity.')).toHaveCount(0);
  });

  test('shows charts but NO map when latlng is missing', async ({ page }) => {
    const id = await findActivityIdByName(page, 'Track Session 3x1km', '3x1km intervals');
    await page.goto(`/activities/${id}/detail`);

    await expect(page.locator('#activityMap')).toHaveCount(0);
    await expect(page.locator('canvas#paceChart')).toBeVisible();
    await expect(page.locator('canvas#hrChart')).toBeVisible();
  });

  test('shows only pace chart when both latlng and heartrate are missing', async ({ page }) => {
    const id = await findActivityIdByName(page, 'Pace Only Run', 'easy 9km');
    await page.goto(`/activities/${id}/detail`);

    await expect(page.locator('#activityMap')).toHaveCount(0);
    await expect(page.locator('canvas#paceChart')).toBeVisible();
    await expect(page.locator('canvas#hrChart')).toHaveCount(0);
  });

  test('shows no-data message when streams are null', async ({ page }) => {
    const id = await findActivityIdByName(page, 'No Data Run', 'easy 9km');
    await page.goto(`/activities/${id}/detail`);

    await expect(page.locator('#activityMap')).toHaveCount(0);
    await expect(page.locator('canvas#paceChart')).toHaveCount(0);
    await expect(page.locator('canvas#hrChart')).toHaveCount(0);
    await expect(page.getByText('No stream data available for this activity.')).toBeVisible();
  });

  test('displays activity card with correct stats', async ({ page }) => {
    const id = await findActivityIdByName(page, 'Easy New Year 9km', 'easy 9km');
    await page.goto(`/activities/${id}/detail`);

    // Activity name
    await expect(page.locator('h3:has-text("Easy New Year 9km")')).toBeVisible();

    // Stats labels present
    await expect(page.locator('dt:has-text("Distance")')).toBeVisible();
    await expect(page.locator('dt:has-text("Pace")')).toBeVisible();
    await expect(page.locator('dt:has-text("Duration")')).toBeVisible();
    await expect(page.locator('dt:has-text("Avg HR")')).toBeVisible();
    await expect(page.locator('dt:has-text("Gear")')).toBeVisible();

    // Specific values
    await expect(page.locator('dd:has-text("Nike Pegasus 40")')).toBeVisible();
    // Distance dd contains "km" but exclude pace which also has "km"
    await expect(page.locator('dd', { hasText: /^\d+\.\d+ km$/ })).toBeVisible();
    // HR value present (Avg HR and Max HR both show bpm)
    await expect(page.locator('dd', { hasText: /\d+ bpm/ }).first()).toBeVisible();
  });

  test('shows pattern type badge', async ({ page }) => {
    const id = await findActivityIdByName(page, 'Easy New Year 9km', 'easy 9km');
    await page.goto(`/activities/${id}/detail`);

    await expect(page.locator('span:has-text("Steady")')).toBeVisible();
  });

  test('shows em-dash for null fields', async ({ page }) => {
    // "Exploration Run" has null HR and null gear — find via calendar (Nov 2025)
    const id = await findActivityIdByName(page, 'Exploration Run');
    await page.goto(`/activities/${id}/detail`);

    const dashes = page.locator('dd:has-text("—")');
    const count = await dashes.count();
    expect(count).toBeGreaterThanOrEqual(2);
  });

  test('shows correct Strava external link', async ({ page }) => {
    const id = await findActivityIdByName(page, 'Easy New Year 9km', 'easy 9km');
    await page.goto(`/activities/${id}/detail`);

    const stravaLink = page.locator('a[href^="https://www.strava.com/activities/"]');
    await expect(stravaLink).toBeVisible();
    const href = await stravaLink.getAttribute('href');
    expect(href).toMatch(/https:\/\/www\.strava\.com\/activities\/\d+/);
  });

  test('shows pattern signature link in card', async ({ page }) => {
    const id = await findActivityIdByName(page, 'Easy New Year 9km', 'easy 9km');
    await page.goto(`/activities/${id}/detail`);

    await expect(page.locator('a:has-text("easy 9km")')).toBeVisible();
  });

  test('shows unclassified badge for unclassified activity', async ({ page }) => {
    // "Exploration Run" — find via calendar
    const id = await findActivityIdByName(page, 'Exploration Run');
    await page.goto(`/activities/${id}/detail`);

    await expect(page.locator('span:has-text("Unclassified")')).toBeVisible();
  });

  test('has back navigation', async ({ page }) => {
    const id = await findActivityIdByName(page, 'Easy New Year 9km', 'easy 9km');
    await page.goto(`/activities/${id}/detail`);

    await expect(page.getByText('Back')).toBeVisible();
  });

});
