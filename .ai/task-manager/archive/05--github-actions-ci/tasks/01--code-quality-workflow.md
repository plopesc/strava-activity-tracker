---
id: 1
group: "github-actions-ci"
dependencies: []
status: "completed"
created: "2026-02-23"
skills:
  - github-actions
  - php
---
# Code Quality Workflow

## Objective

Create `.github/workflows/code-quality.yml` to run PHPStan level 8 and PHP-CS-Fixer on every push and pull request to main, blocking merges that introduce type violations or formatting differences.

## Skills Required

- github-actions: authoring workflow YAML, using actions/checkout, shivammathur/setup-php, actions/cache
- php: understanding Composer script aliases, PHPStan, PHP-CS-Fixer invocation

## Acceptance Criteria

- [ ] File `.github/workflows/code-quality.yml` exists and is valid GitHub Actions YAML
- [ ] Workflow triggers on `push` to `main` and `pull_request` targeting `main`
- [ ] PHP 8.4 is provisioned with extensions: `pdo`, `pdo_mysql`, `intl`, `mbstring`
- [ ] Composer dependencies are installed with a cache keyed on `composer.lock` hash
- [ ] `APP_ENV=test` is set as a job-level environment variable
- [ ] `composer phpstan` runs and fails the workflow on violations
- [ ] `composer php-cs-fixer:check` runs and fails the workflow on formatting diffs
- [ ] No database service is configured (not needed for static analysis)

## Technical Requirements

- Actions to use: `actions/checkout@v4`, `shivammathur/setup-php@v2`, `actions/cache@v4`
- PHP version: `8.4`
- PHP extensions: `pdo, pdo_mysql, intl, mbstring`
- Composer cache path: output of `composer config cache-files-dir`
- Cache key pattern: `composer-${{ hashFiles('composer.lock') }}`
- Environment variable: `APP_ENV=test` (required for Symfony kernel bootstrap during PHPStan analysis)
- Composer scripts to invoke: `composer phpstan`, `composer php-cs-fixer:check`
- Runner: `ubuntu-latest`

## Input Dependencies

None — this task has no dependencies on other tasks.

## Output Artifacts

- `.github/workflows/code-quality.yml`

## Implementation Notes

<details>
<summary>Workflow structure guidance</summary>

The workflow file should follow this structure:

1. Set `name: Code Quality` at the top level.

2. Define `on:` triggers:
   ```yaml
   on:
     push:
       branches: [main]
     pull_request:
       branches: [main]
   ```

3. Define a single job (e.g., `quality`) running on `ubuntu-latest` with `env: APP_ENV: test` at the job level.

4. Steps in order:
   - `actions/checkout@v4` (no special config needed)
   - `shivammathur/setup-php@v2` with `php-version: '8.4'`, `extensions: pdo, pdo_mysql, intl, mbstring`, `coverage: none`
   - Get Composer cache directory: run `echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT` in a step with `id: composer-cache`
   - `actions/cache@v4` with `path: ${{ steps.composer-cache.outputs.dir }}` and `key: composer-${{ hashFiles('composer.lock') }}`
   - `composer install --prefer-dist --no-progress`
   - `composer phpstan`
   - `composer php-cs-fixer:check`

5. The `APP_ENV=test` env var at the job level satisfies Symfony's kernel bootstrap, which PHPStan requires to reflect on service container types.

6. No `continue-on-error` — both steps must gate the build (non-zero exit = workflow failure).

7. The PHPStan baseline file (`phpstan-baseline.neon` if it exists) is committed to the repo and automatically loaded by the PHPStan config — no CI-specific flag needed.
</details>
