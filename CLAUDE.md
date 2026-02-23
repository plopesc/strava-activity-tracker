# CLAUDE.md

## Project Overview

Symfony 7 web application that integrates with the Strava API to sync running activities and visualizes activity patterns using Chart.js. Tracks and analyzes running patterns to identify recurring routes, pace trends, and performance metrics.

## Tech Stack

- **PHP 8.4** with **Symfony 8.0** (full-stack framework)
- **MariaDB 11.8** via DDEV, accessed through **Doctrine ORM 3.x**
- **Twig** for templating, **Chart.js** for frontend visualizations
- **DDEV** for local development environment
- **PHPUnit 13** for unit testing, **Playwright** for E2E testing
- **Tailwind CSS** via `symfonycasts/tailwind-bundle` (standalone binary, no Node.js)
- **Hotwire**: Turbo Drive + Turbo Frames (`symfony/ux-turbo`)
- **Stimulus** (`symfony/stimulus-bundle`) for client-side interactivity
- **Symfony AssetMapper** with importmap for JS/CSS assets

## Local Development

If DDEV is available, use ddev. Otherwise, it means that you're inside the container and can obbiate the ddev prefix and command can be run directly in the console. 

```bash
ddev start                    # Start environment
ddev composer install         # Install PHP dependencies
ddev launch                   # Open https://strava.ddev.site
```

**Console commands:**
```bash
ddev exec php bin/console strava:sync                    # Incremental activity sync
ddev exec php bin/console strava:sync --force            # Force full re-sync of all activities
ddev exec php bin/console strava:sync --limit 50         # Fetch limited number of activities
ddev exec php bin/console strava:classify <id>           # Classify a single activity by ID
ddev exec php bin/console doctrine:migrations:migrate
ddev exec php bin/console debug:router
ddev exec php bin/console tailwind:build           # Compile Tailwind CSS
ddev exec php bin/console tailwind:build --watch   # Watch mode for development
ddev exec php bin/console asset-map:compile        # Compile all assets
ddev exec php bin/console importmap:require <pkg>  # Add a JS package
```

**Run tests:**
```bash
ddev exec php vendor/bin/phpunit --configuration phpunit.dist.xml
ddev exec php vendor/bin/phpunit --configuration phpunit.dist.xml tests/Pattern/PatternRecognizerTest.php
```

**E2E tests (Playwright):**
```bash
ddev exec bash scripts/run-e2e.sh                               # Full suite (setup DB + fixtures + run)
ddev exec npx playwright test                                    # Run tests only (DB must be ready)
ddev exec npx playwright test tests/e2e/calendar.spec.ts         # Run specific test file
ddev exec npx playwright test --headed                           # Run with visible browser
```

**E2E test database setup (manual):**
```bash
ddev exec php bin/console doctrine:database:create --if-not-exists  # Uses db_e2e via .env.test
ddev exec php bin/console doctrine:schema:update --force
DATABASE_URL="mysql://root:root@db:3306/db_e2e?sslmode=disable&charset=utf8mb4&serverVersion=11.8.0-mariadb" \
  ddev exec php bin/console doctrine:fixtures:load --no-interaction
```

## CI (GitHub Actions)

Three workflows run automatically on push and pull request to `main`:

| Workflow | File | What it checks |
|---|---|---|
| Code Quality | `.github/workflows/code-quality.yml` | PHPStan level 8 + PHP-CS-Fixer formatting (no DB required) |
| PHPUnit | `.github/workflows/phpunit.yml` | Full unit test suite against a MariaDB 11.8 service container |
| E2E Tests | `.github/workflows/e2e.yml` | Playwright browser tests via DDEV (`ddev exec bash scripts/run-e2e.sh`) |

**On E2E failure:** Download the `playwright-report` artifact from the GitHub Actions run summary (Actions tab → failed run → Artifacts section) to view screenshots, traces, and error details. Artifacts are retained for 7 days.

## Project Structure

```
src/
├── Command/        # Console commands (StravaSyncCommand, StravaClassifyCommand)
├── Controller/     # HTTP controllers (ActivityController, ComparisonController)
├── Entity/         # Doctrine entities (Activity, Gear)
├── Pattern/        # Pattern recognition logic (PatternRecognizer)
├── Repository/     # Database repositories (ActivityRepository)
├── Service/        # Business logic services (ActivitySyncProcessor)
├── Strava/         # Strava API integration (StravaClient)
└── Twig/           # Twig extensions
assets/
├── app.js              # AssetMapper entry point
├── controllers/        # Stimulus controllers
│   ├── calendar-selection_controller.js
│   ├── sortable-table_controller.js
│   └── comparison-selector_controller.js
└── styles/
    └── app.css         # Tailwind directives
templates/
├── base.html.twig
├── activity/       # Activity-specific views
│   ├── card.html.twig        # Turbo Frame sidebar card (used by calendar + detail page)
│   ├── detail.html.twig      # Full-page activity detail
│   └── comparison.html.twig
├── calendar/
│   └── index.html.twig       # Monthly calendar grid
└── pattern/
    ├── list.html.twig         # Pattern groups with recent activities
    └── detail.html.twig       # Paginated sortable table for a single pattern
migrations/         # Doctrine database migrations
tests/
├── e2e/            # Playwright E2E tests
│   ├── smoke.spec.ts           # Basic smoke test
│   ├── calendar.spec.ts        # Calendar page tests
│   ├── patterns.spec.ts        # Pattern list + detail tests
│   └── activity-detail.spec.ts # Activity detail page tests
└── Pattern/        # PHPUnit tests (PatternRecognizerTest)
```

## Environment Configuration

Copy `.env.local.example` to `.env.local` and fill in Strava credentials:

```
STRAVA_CLIENT_ID=<your_client_id>
STRAVA_CLIENT_SECRET=<your_client_secret>
STRAVA_REFRESH_TOKEN=<your_refresh_token>
STRAVA_ACCESS_TOKEN=<your_access_token>
STRAVA_TOKEN_EXPIRES_AT=<epoch_timestamp>
```

Strava tokens are cached in `var/strava-token.json` (git-ignored) and automatically refreshed when expired.

## Architecture Notes

- **StravaClient** handles OAuth 2.0 token management, API calls, and rate limiting (100 req/15 min)
- **PatternRecognizer** classifies activities into pattern types and generates pattern signatures
- **ActivitySyncProcessor** encapsulates activity data mapping, gear handling, and classification (shared by StravaSyncCommand and StravaClassifyCommand)
- **StravaSyncCommand** orchestrates incremental and full syncs with bulk processing
- **StravaClassifyCommand** classifies individual activities from the Strava API
- Database schema: single `activity` table with pattern classification columns and JSON fields for raw laps/streams

## Web Routes

- `GET /` — Redirects to `/activities`
- `GET /activities` — Calendar view (monthly grid with activity icons) (`activity_calendar`)
- `GET /activities/{id}/detail` — Full-page activity detail; `{id}` is the database primary key (`activity_detail`). Also serves as a Turbo Frame response — the calendar sidebar extracts `<turbo-frame id="activity-detail">` from the full page automatically.
- `GET /activities/pattern` — Pattern list (alphabetical groups with recent activities) (`activity_pattern_list`)
- `GET /activities/pattern/{signature}` — Pattern detail with paginated sortable table (`activity_pattern_detail`)
- `GET /activities/compare` — Comparison view with Chart.js trend panels (`activity_compare`)

## Frontend Verification

Use the `playwright-cli` skill for browser verification of frontend changes:
- Navigate pages, take screenshots, and interact with elements
- No npm setup required — the skill manages browser infrastructure internally
- Example: navigate to `https://strava.ddev.site/activities` and verify the calendar renders

## Code Quality & Linting

**PHPStan** — Static type analysis at level 8:
```bash
ddev composer phpstan           # Run analysis
ddev composer phpstan:baseline  # Generate baseline for ignoring issues
```

**PHP-CS-Fixer** — Automatic code formatting (PSR-12 + Symfony):
```bash
ddev composer php-cs-fixer       # Fix code style in-place
ddev composer php-cs-fixer:check # Check formatting without changes
```

**Combined Lint Check:**
```bash
ddev composer lint  # Run both phpstan and php-cs-fixer:check
```

## Coding Standards

Follow **Symfony coding standards** and best practices:

- Use **early returns** to reduce nesting and cyclomatic complexity
- **Avoid code duplication** — extract common logic into reusable methods
- Follow PSR-12 coding style (enforced by PHP_CodeSniffer if configured)
- Use type hints and return types for all methods
- Keep methods focused and single-responsibility
- Prefer dependency injection over service location
- Uses PHP 8.4+ with strict typing and modern PHP features

**Example: Early returns**

```php
// ❌ Avoid deep nesting
public function process($data): void
{
    if ($data !== null) {
        if ($this->isValid($data)) {
            // ... complex logic
        }
    }
}

// ✅ Use early returns
public function process($data): void
{
    if ($data === null) {
        return;
    }

    if (!$this->isValid($data)) {
        return;
    }

    // ... complex logic
}
```

## Task Management

This project uses an AI task management system in `.ai/task-manager/`. Use the available skills:
- `/tasks:create-plan` — Create a plan for a feature
- `/tasks:generate-tasks` — Generate tasks from a plan
- `/tasks:execute-task` — Execute a specific task
- `/tasks:full-workflow` — Run the full plan → task → execute workflow
