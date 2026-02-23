---
id: 2
group: "github-actions-ci"
dependencies: []
status: "completed"
created: "2026-02-23"
skills:
  - github-actions
  - php
---
# PHPUnit Workflow

## Objective

Create `.github/workflows/phpunit.yml` to run the full PHPUnit 13 test suite in CI against a MariaDB 11.8 service container, giving the team automated unit test feedback on every push and pull request to main.

## Skills Required

- github-actions: service containers, environment variable injection, health checks, actions/cache
- php: Symfony console commands, Doctrine schema creation, PHPUnit invocation via bin/console

## Acceptance Criteria

- [ ] File `.github/workflows/phpunit.yml` exists and is valid GitHub Actions YAML
- [ ] Workflow triggers on `push` to `main` and `pull_request` targeting `main`
- [ ] PHP 8.4 is provisioned with extensions: `pdo`, `pdo_mysql`, `intl`, `mbstring`
- [ ] A MariaDB 11.8 service container starts with `MYSQL_ROOT_PASSWORD=root` and `MYSQL_DATABASE=db_test`
- [ ] The service container port `3306` is mapped to host port `3306`
- [ ] A health check using `mysqladmin ping` prevents subsequent steps from running until MariaDB is ready
- [ ] `DATABASE_URL` env var points to the service container at `127.0.0.1:3306`
- [ ] Composer dependencies are installed with a cache keyed on `composer.lock` hash
- [ ] `php bin/console doctrine:schema:create --env=test` runs before the test suite
- [ ] `php bin/console phpunit` runs and fails the workflow on test failures
- [ ] No Strava API credentials are injected

## Technical Requirements

- Actions to use: `actions/checkout@v4`, `shivammathur/setup-php@v2`, `actions/cache@v4`
- PHP version: `8.4`
- PHP extensions: `pdo, pdo_mysql, intl, mbstring`
- MariaDB service image: `mariadb:11.8`
- Service env vars: `MYSQL_ROOT_PASSWORD: root`, `MYSQL_DATABASE: db_test`
- Service port mapping: `3306:3306`
- Health check command: `mysqladmin ping -h 127.0.0.1 -u root --password=root`
- Health check options: `interval: 10s`, `timeout: 5s`, `retries: 5`
- `DATABASE_URL` value: `mysql://root:root@127.0.0.1:3306/db_test?sslmode=disable&charset=utf8mb4&serverVersion=11.8.0-mariadb`
- `APP_ENV`: `test`
- Composer cache key: `composer-${{ hashFiles('composer.lock') }}`
- Runner: `ubuntu-latest`

## Input Dependencies

None — this task has no dependencies on other tasks.

## Output Artifacts

- `.github/workflows/phpunit.yml`

## Implementation Notes

<details>
<summary>Workflow structure guidance</summary>

The workflow file should follow this structure:

1. Set `name: PHPUnit` at the top level.

2. Define `on:` triggers for `push` to `main` and `pull_request` targeting `main`.

3. Define a single job (e.g., `tests`) on `ubuntu-latest`.

4. Set job-level `env`:
   ```yaml
   env:
     APP_ENV: test
     DATABASE_URL: "mysql://root:root@127.0.0.1:3306/db_test?sslmode=disable&charset=utf8mb4&serverVersion=11.8.0-mariadb"
   ```

5. Define the `services` block at the job level (not step level):
   ```yaml
   services:
     mariadb:
       image: mariadb:11.8
       env:
         MYSQL_ROOT_PASSWORD: root
         MYSQL_DATABASE: db_test_test
       ports:
         - 3306:3306
       options: >-
         --health-cmd="mysqladmin ping -h 127.0.0.1 -u root --password=root"
         --health-interval=10s
         --health-timeout=5s
         --health-retries=5
   ```

6. Steps in order:
   - `actions/checkout@v4`
   - `shivammathur/setup-php@v2` with `php-version: '8.4'`, `extensions: pdo, pdo_mysql, intl, mbstring`, `coverage: none`
   - Get Composer cache dir step with output `dir`
   - `actions/cache@v4` for Composer cache
   - `composer install --prefer-dist --no-progress`
   - `php bin/console doctrine:schema:create --env=test`
   - `php bin/console phpunit`

7. The `.env.test` file already sets `db_test` as the database name and the test environment; the `DATABASE_URL` env var at the job level overrides the DSN to target the CI service container rather than a DDEV container.

8. No `doctrine:database:create` step is needed because the MariaDB service creates `db_test` automatically via `MYSQL_DATABASE`.

9. Do not add `--no-interaction` to the phpunit command — `php bin/console phpunit` is the correct invocation matching the local workflow.
</details>
