---
id: 1
group: "test-infrastructure"
dependencies: []
status: "pending"
created: "2026-02-23"
skills: ["symfony", "ddev", "npm"]
---
# Set Up Playwright Test Infrastructure and Doctrine Fixtures

## Objective
Install Playwright as a project dependency, configure a separate MariaDB test database in DDEV, install and configure Doctrine fixtures bundle, create comprehensive test fixtures (~20-25 activities), and set up the test orchestration script.

## Skills Required
- `symfony`: Doctrine fixtures bundle installation, test environment configuration, console commands
- `ddev`: Additional database configuration for test environment
- `npm`: Package.json creation, @playwright/test installation and configuration

## Acceptance Criteria
- [ ] `package.json` exists at project root with `@playwright/test` as devDependency
- [ ] `playwright.config.ts` exists targeting `https://strava.ddev.site`, using Chromium, with `ignoreHTTPSErrors: true`, test dir `tests/e2e/`
- [ ] DDEV configured with a second MariaDB database (e.g., `db_test`)
- [ ] Symfony `test` environment configured with `DATABASE_URL` pointing to the test database in `.env.test`
- [ ] `doctrine/doctrine-fixtures-bundle` installed via Composer
- [ ] Fixture class creates ~20-25 activities across 4-5 months with:
  - 3-4 pattern groups (at least one with 5+ activities for pagination potential, one with 2-3, one with 1)
  - Several unclassified activities (null patternType and patternSignature)
  - 3 gear items distributed across activities
  - Activities with full streams (latlng + heartrate + velocity_smooth in rawStreams)
  - Activities with partial streams (velocity_smooth only, no heartrate)
  - Activities with no streams (null rawStreams)
  - Activities with null averageHeartrate, null gear
- [ ] A shell script or Makefile target orchestrates: create/reset test DB schema → load fixtures → run Playwright
- [ ] Chromium browser installed via `npx playwright install chromium`
- [ ] `tests/e2e/` directory created with a trivial smoke test that navigates to `/activities` and verifies the page title
- [ ] PHPStan and PHP-CS-Fixer pass

Use your internal Todo tool to track these and keep on track.

## Technical Requirements
- Symfony test environment (`APP_ENV=test`) with separate `DATABASE_URL`
- DDEV `config.yaml` or `.ddev/docker-compose.*.yaml` for additional test DB
- `doctrine/doctrine-fixtures-bundle` for fixture loading
- Fixture data must use fixed dates (not relative) so assertions are deterministic — use dates in the past (e.g., Oct 2025 – Jan 2026) so the calendar "future month guard" can be tested
- rawStreams JSON should contain realistic but minimal data structures matching what Strava API returns
- rawLaps can be null or minimal for simplicity
- `playwright.config.ts` should configure: `baseURL`, `use.ignoreHTTPSErrors`, `testDir`, `projects` (chromium only), reasonable `timeout` and `expect.timeout`

## Input Dependencies
None — this is the foundational task.

## Output Artifacts
- `package.json` and `package-lock.json`
- `playwright.config.ts`
- `tests/e2e/` directory with smoke test
- DDEV test database configuration
- `.env.test` with test DATABASE_URL
- `src/DataFixtures/TestActivityFixtures.php` (or similar)
- Test orchestration script (e.g., `scripts/run-e2e.sh` or Makefile target)

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### Step 1: Install Playwright
```bash
npm init -y
npm install --save-dev @playwright/test
npx playwright install chromium
```

### Step 2: Create playwright.config.ts
Configure with:
- `baseURL: 'https://strava.ddev.site'`
- `use: { ignoreHTTPSErrors: true }`
- `testDir: './tests/e2e'`
- Single Chromium project
- `timeout: 30000`, `expect: { timeout: 5000 }`

### Step 3: Configure DDEV test database
Add a `.ddev/docker-compose.testdb.yaml` or modify DDEV config to expose a `db_test` database. Alternatively, use DDEV's built-in hooks or a post-start command to create the test database.

A simpler approach: use the same MariaDB server but a different database name. In `.env.test`:
```
DATABASE_URL="mysql://db:db@db:3306/db_test?serverVersion=11.8.1-MariaDB"
```
Then create the database with `php bin/console doctrine:database:create --env=test`.

### Step 4: Install Doctrine Fixtures Bundle
```bash
composer require --dev doctrine/doctrine-fixtures-bundle
```

### Step 5: Create fixtures
Create `src/DataFixtures/TestActivityFixtures.php` implementing `FixtureInterface`. Use the Activity and Gear entities directly.

Key data points:
- Fixed dates: Oct 2025 activities, Nov 2025 activities, Dec 2025 activities, Jan 2026 activities (4 months)
- Pattern groups: "easy 9km" (5-6 activities), "3x1km intervals" (3 activities), "long sunday run" (2 activities), plus 3-4 unclassified
- Gear: "Nike Pegasus 40", "ASICS Gel-Nimbus 25", "Brooks Ghost 15"
- Full streams example: `{"velocity_smooth": [2.5, 2.6, ...], "distance": [0, 10, ...], "heartrate": [120, 125, ...], "latlng": [[41.38, 2.17], [41.381, 2.171], ...]}`
- Partial streams: same but without `heartrate` and/or `latlng` keys
- Distances in meters (e.g., 9000 for 9km), elapsedTime in seconds, averageSpeed in m/s

### Step 6: Create test orchestration script
```bash
#!/bin/bash
# scripts/run-e2e.sh
set -e
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:schema:update --env=test --force
php bin/console doctrine:fixtures:load --env=test --no-interaction
npx playwright test
```

### Step 7: Smoke test
Create `tests/e2e/smoke.spec.ts`:
```typescript
import { test, expect } from '@playwright/test';
test('homepage redirects to calendar', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveURL(/\/activities/);
});
```

</details>
