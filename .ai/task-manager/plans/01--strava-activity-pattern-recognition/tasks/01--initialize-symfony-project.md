---
id: 1
group: "project-setup"
dependencies: []
status: "pending"
created: 2026-02-18
skills:
  - php
  - symfony
---
# Initialize Symfony Project

## Objective
Bootstrap a Symfony 7 project with all required dependencies, configure the SQLite database connection, and establish the foundational directory structure and environment configuration so subsequent tasks can build on a working skeleton.

## Skills Required
- PHP / Composer dependency management
- Symfony framework configuration

## Acceptance Criteria
- [ ] Symfony 7 skeleton is created and `bin/console` runs without errors
- [ ] All required Composer packages are installed (`doctrine/orm`, `doctrine/dbal`, `symfony/http-client`, `symfony/twig-bundle`, `symfony/asset`, `pdo_sqlite`)
- [ ] SQLite database file path is configured in `.env` (e.g., `DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db`)
- [ ] `.env.local` is git-ignored and contains a template for Strava credentials (`STRAVA_CLIENT_ID`, `STRAVA_CLIENT_SECRET`, `STRAVA_REFRESH_TOKEN`)
- [ ] Doctrine migrations bundle is installed and `bin/console doctrine:migrations:status` runs cleanly
- [ ] A base Twig layout template exists (`templates/base.html.twig`) with a Chart.js CDN script tag included
- [ ] `README.md` documents the initial setup steps (clone, composer install, copy `.env.local`, configure Strava credentials, run migrations)

## Technical Requirements
- PHP 8.2+
- Composer
- Symfony 7.x (`symfony/framework-bundle`, `symfony/console`, `symfony/twig-bundle`, `symfony/asset`, `symfony/http-client`)
- `doctrine/orm`, `doctrine/dbal` with SQLite PDO driver (`pdo_sqlite` PHP extension)
- `doctrine/migrations` for schema management
- Chart.js via CDN (no local build step)

## Input Dependencies
None — this is the foundation task.

## Output Artifacts
- Symfony project skeleton at the repository root
- Configured `.env` with SQLite `DATABASE_URL`
- `.env.local.example` with Strava credential placeholders
- `templates/base.html.twig` with Chart.js CDN included
- `README.md` with setup instructions

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

1. **Create the project**: Run `composer create-project symfony/skeleton .` (or in the existing directory) to scaffold a minimal Symfony app. Use Symfony 7.

2. **Install required packages**:
   ```
   composer require doctrine/orm doctrine/dbal symfony/http-client symfony/twig-bundle symfony/asset doctrine/doctrine-migrations-bundle
   ```
   The `pdo_sqlite` PHP extension must be enabled in `php.ini`.

3. **Configure SQLite**: In `.env`, set:
   ```
   DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
   ```

4. **Create `.env.local.example`** with:
   ```
   STRAVA_CLIENT_ID=
   STRAVA_CLIENT_SECRET=
   STRAVA_REFRESH_TOKEN=
   STRAVA_ACCESS_TOKEN=
   STRAVA_TOKEN_EXPIRES_AT=0
   ```
   Add `.env.local` to `.gitignore` (Symfony does this by default). Commit `.env.local.example` for reference.

5. **Token file location**: The refreshed token will be stored in `var/strava-token.json` (created at runtime). Add `var/strava-token.json` to `.gitignore`.

6. **Base Twig layout**: Create `templates/base.html.twig` with a standard HTML shell. In the `<head>`, include:
   ```html
   <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   ```
   Add a `{% block content %}{% endblock %}` body placeholder.

7. **README.md**: Document: prerequisites (PHP 8.2+, Composer), initial setup steps (clone → `composer install` → copy `.env.local.example` to `.env.local` → fill Strava credentials → `php bin/console doctrine:migrations:migrate` → `symfony server:start`), and how to obtain initial Strava credentials (link to developers.strava.com OAuth flow).

</details>
