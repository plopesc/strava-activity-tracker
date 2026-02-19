# CLAUDE.md

## Project Overview

Symfony 7 web application that integrates with the Strava API to sync running activities and visualizes activity patterns using Chart.js. Tracks and analyzes running patterns to identify recurring routes, pace trends, and performance metrics.

## Tech Stack

- **PHP 8.4** with **Symfony 8.0** (full-stack framework)
- **MariaDB 11.8** via DDEV, accessed through **Doctrine ORM 3.x**
- **Twig** for templating, **Chart.js** for frontend visualizations
- **DDEV** for local development environment
- **PHPUnit 13** for testing

## Local Development

All commands run inside DDEV:

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
```

**Run tests:**
```bash
ddev exec php bin/console phpunit
ddev exec php bin/console phpunit tests/Pattern/PatternRecognizerTest.php
```

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
templates/
├── base.html.twig
└── activity/       # Activity list, pattern group, comparison views
migrations/         # Doctrine database migrations
tests/
└── Pattern/        # PatternRecognizerTest
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

- `/activities` — Activity list grouped by pattern
- `/activities/pattern/{signature}` — Pattern group detail view
- Comparison views with Chart.js trend panels

## Coding Standards

Follow **Symfony coding standards** and best practices:

- Use **early returns** to reduce nesting and cyclomatic complexity
- **Avoid code duplication** — extract common logic into reusable methods
- Follow PSR-12 coding style (enforced by PHP_CodeSniffer if configured)
- Use type hints and return types for all methods
- Keep methods focused and single-responsibility
- Prefer dependency injection over service location

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
