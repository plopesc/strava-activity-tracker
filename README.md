# Strava Activity Checker

A sophisticated Symfony 8 web application that syncs running activities from the Strava API and visualizes activity patterns using Chart.js. Automatically classifies activities into recurring routes and generates insights into your running performance.

## Features

- 🏃 **Activity Sync** — Incremental and full resync of Strava running activities
- 📊 **Pattern Recognition** — Automatically detects and classifies recurring routes
- 📈 **Performance Analytics** — Visualize pace trends and performance metrics
- ⚙️ **Smart Limits** — Control sync volume with configurable activity limits
- 🔄 **Automatic Token Management** — Handles OAuth 2.0 refresh tokens transparently

## Prerequisites

- [Git](https://git-scm.com/)
- [DDEV](https://ddev.readthedocs.io/en/stable/) (v1.22+)

## Quick Start

### 1. Clone and setup the project

```bash
git clone <repository-url>
cd strava-activity-checker
ddev start
ddev composer install
```

### 2. Configure Strava API credentials

Copy the example environment file:

```bash
cp .env.local.example .env.local
```

Edit `.env.local` and add your Strava credentials (see [Obtaining Strava Credentials](#obtaining-strava-credentials) below).

### 3. Initialize the database

```bash
ddev exec php bin/console doctrine:migrations:migrate
```

### 4. Launch the application

```bash
ddev launch
```

The app will open at [https://strava-activity-checker.ddev.site](https://strava-activity-checker.ddev.site).

## Obtaining Strava Credentials

### Step 1: Register your API application

1. Visit [Strava API Settings](https://www.strava.com/settings/api)
2. Create a new application
3. Set the **Authorization Callback Domain** to `strava-activity-checker.ddev.site`
4. Note your **Client ID** and **Client Secret**

### Step 2: Get authorization tokens

Use the OAuth 2.0 authorization flow:

1. Visit this URL in your browser:
   ```
   https://www.strava.com/oauth/authorize?client_id=<CLIENT_ID>&response_type=code&redirect_uri=https://strava-activity-checker.ddev.site/strava/callback&approval_prompt=force&scope=activity:read_all
   ```

2. Authorize the app. Strava will redirect to a callback URL with a `code` parameter.

3. Exchange the code for tokens using curl:
   ```bash
   curl -X POST https://www.strava.com/oauth/token \
     -d client_id=<CLIENT_ID> \
     -d client_secret=<CLIENT_SECRET> \
     -d code=<CODE> \
     -d grant_type=authorization_code
   ```

4. Copy the tokens from the response into `.env.local`:

   ```dotenv
   STRAVA_CLIENT_ID=your_client_id
   STRAVA_CLIENT_SECRET=your_client_secret
   STRAVA_REFRESH_TOKEN=your_refresh_token
   STRAVA_ACCESS_TOKEN=your_access_token
   STRAVA_TOKEN_EXPIRES_AT=1700000000
   ```

## Usage

### Syncing Activities

Sync incrementally (new activities only):
```bash
ddev exec php bin/console strava:sync
```

Force a full re-sync of all activities:
```bash
ddev exec php bin/console strava:sync --force
```

Limit the number of activities fetched:
```bash
ddev exec php bin/console strava:sync --limit=50
```

### Classifying a Single Activity

Classify an individual activity by its Strava ID:
```bash
ddev exec php bin/console strava:classify 123456789
```

Use `--dry-run` to preview classification without persisting:
```bash
ddev exec php bin/console strava:classify 123456789 --dry-run
```

### Accessing the Web Interface

Open [https://strava-activity-checker.ddev.site](https://strava-activity-checker.ddev.site) to view:
- Activity list grouped by pattern
- Pattern detail views with performance metrics
- Comparison trends with Chart.js visualizations

## Development

### Running Tests

```bash
ddev exec phpunit
ddev exec phpunit tests/Pattern/PatternRecognizerTest.php
```

### Common Commands

```bash
# PHP console
ddev exec php bin/console <command>

# Composer
ddev composer <command>

# Database
ddev exec php bin/console doctrine:migrations:migrate
ddev exec php bin/console doctrine:migrations:status
ddev exec php bin/console doctrine:database:create
ddev exec php bin/console doctrine:database:drop --force

# Debugging
ddev exec php bin/console debug:router
ddev exec php bin/console debug:container
```

## Architecture

This application uses:

- **Symfony 8.0** — Full-stack PHP framework with routing, templating, and ORM
- **Doctrine ORM 3.x** — Database abstraction and entity management
- **MariaDB 11.8** — Data persistence via DDEV
- **Chart.js** — Interactive frontend data visualization
- **PHPUnit 13** — Test framework and test suite

See [CLAUDE.md](CLAUDE.md) for detailed architecture documentation and coding standards.

## Project Structure

```
src/
├── Command/        # Console commands (sync, classify)
├── Controller/     # HTTP controllers
├── Entity/         # Doctrine entities
├── Pattern/        # Pattern recognition logic
├── Repository/     # Database repositories
├── Service/        # Business logic services
├── Strava/         # Strava API integration
└── Twig/           # Template extensions
```

## Contributing

See [CLAUDE.md](CLAUDE.md) for coding standards and contribution guidelines.

## License

See LICENSE file for details.
