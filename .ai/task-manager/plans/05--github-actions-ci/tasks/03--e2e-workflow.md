---
id: 3
group: "github-actions-ci"
dependencies: []
status: "completed"
created: "2026-02-23"
skills:
  - github-actions
  - playwright
---
# Playwright E2E Workflow

## Objective

Create `.github/workflows/e2e.yml` to run the full Playwright E2E test suite in CI against a live DDEV-hosted application, reusing `scripts/run-e2e.sh` without modification and uploading the Playwright HTML report as an artifact on failure.

## Skills Required

- github-actions: ddev/setup-ddev action, actions/cache for Docker layers, artifact upload with conditional execution
- playwright: browser installation (chromium --with-deps), report artifact patterns

## Acceptance Criteria

- [ ] File `.github/workflows/e2e.yml` exists and is valid GitHub Actions YAML
- [ ] Workflow triggers on `push` to `main` and `pull_request` targeting `main`
- [ ] `ddev/setup-ddev@v1` action is used to install and configure DDEV
- [ ] `ddev start` brings up the full stack before any application steps
- [ ] `ddev composer install` installs PHP dependencies inside the DDEV web container
- [ ] `actions/setup-node@v4` provisions Node.js 20 on the host runner
- [ ] `npx playwright install chromium --with-deps` installs Chromium on the host runner
- [ ] `ddev exec bash scripts/run-e2e.sh` runs the full E2E suite (DB setup + fixtures + Playwright)
- [ ] `actions/upload-artifact@v4` uploads `playwright-report/` with 7-day retention, conditional on `if: failure()`
- [ ] Docker layer caching is configured via `actions/cache@v4`
- [ ] No Strava API credentials are injected

## Technical Requirements

- Actions to use: `actions/checkout@v4`, `ddev/setup-ddev@v1`, `actions/cache@v4`, `actions/setup-node@v4`, `actions/upload-artifact@v4`
- Node.js version: `20`
- Playwright browser: `chromium` with `--with-deps` flag (installs OS-level dependencies)
- E2E script: `ddev exec bash scripts/run-e2e.sh` (runs inside DDEV web container; handles DB setup, fixtures, and Playwright execution internally)
- Artifact path: `playwright-report/`
- Artifact retention: `7` days
- Artifact upload condition: `if: failure()`
- Docker cache key: `ddev-docker-${{ runner.os }}-${{ hashFiles('.ddev/config.yaml') }}`
- Runner: `ubuntu-latest`

## Input Dependencies

None — this task has no dependencies on other tasks.

## Output Artifacts

- `.github/workflows/e2e.yml`

## Implementation Notes

<details>
<summary>Workflow structure guidance</summary>

The workflow file should follow this structure:

1. Set `name: E2E Tests` at the top level.

2. Define `on:` triggers for `push` to `main` and `pull_request` targeting `main`.

3. Define a single job (e.g., `e2e`) on `ubuntu-latest`.

4. Steps in order:

   **a. Checkout**
   - `actions/checkout@v4`

   **b. Docker layer cache** (must come before ddev start)
   - `actions/cache@v4`
   - `path`: `/var/lib/docker` (or use the DDEV recommended cache path from `ddev/setup-ddev` docs)
   - `key`: `ddev-docker-${{ runner.os }}-${{ hashFiles('.ddev/config.yaml') }}`
   - `restore-keys`: `ddev-docker-${{ runner.os }}-`

   **c. DDEV setup**
   - `uses: ddev/setup-ddev@v1`

   **d. Start DDEV**
   - `run: ddev start`

   **e. Install PHP dependencies**
   - `run: ddev composer install --prefer-dist --no-progress`

   **f. Set up Node.js on host runner**
   - `uses: actions/setup-node@v4`
   - `with: node-version: '20'`

   **g. Install Playwright browser on host runner**
   - `run: npx playwright install chromium --with-deps`

   **h. Run E2E tests**
   - `run: ddev exec bash scripts/run-e2e.sh`

   **i. Upload Playwright report on failure**
   - `uses: actions/upload-artifact@v4`
   - `if: failure()`
   - `with`:
     - `name: playwright-report`
     - `path: playwright-report/`
     - `retention-days: 7`

5. Key architectural note: Playwright's Node.js process runs on the **host runner**, not inside DDEV. It sends HTTP requests to `https://strava.ddev.site`, which resolves to the DDEV router because `ddev/setup-ddev` configures the runner's DNS. The `playwright.config.ts` `baseURL` and `ignoreHTTPSErrors: true` settings require no changes.

6. The `scripts/run-e2e.sh` script is invoked via `ddev exec` so it runs inside the DDEV web container where `php`, `bin/console`, and `npx` are available. The script handles `doctrine:database:create`, `doctrine:schema:update`, `doctrine:fixtures:load`, and `npx playwright test` internally — no separate database provisioning steps are needed in the workflow.

7. Do not set `APP_ENV` or `DATABASE_URL` at the job level — the DDEV container manages its own environment via `.ddev/config.yaml` and the app's `.env` / `.env.test` files.

8. If `ddev/setup-ddev` documentation recommends a different Docker cache path than `/var/lib/docker`, use whatever the action's README specifies. The key goal is to cache Docker image layers to speed up subsequent runs.
</details>
