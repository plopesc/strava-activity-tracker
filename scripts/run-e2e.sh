#!/bin/bash
# E2E Test Runner
# Loads fixtures into a test database, temporarily switches the app to use it,
# runs Playwright tests, then restores the original database connection.

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_LOCAL="$PROJECT_DIR/.env.local"
ENV_LOCAL_BACKUP="$PROJECT_DIR/.env.local.e2e-backup"
TEST_DB_URL="mysql://root:root@db:3306/db_e2e?sslmode=disable&charset=utf8mb4&serverVersion=11.8.0-mariadb"

cleanup() {
    echo ">>> Restoring original .env.local..."
    if [ -f "$ENV_LOCAL_BACKUP" ]; then
        mv "$ENV_LOCAL_BACKUP" "$ENV_LOCAL"
    else
        rm -f "$ENV_LOCAL"
    fi
    echo ">>> Clearing cache..."
    php "$PROJECT_DIR/bin/console" cache:clear --env=dev --quiet 2>/dev/null || true
    echo ">>> Cleanup done."
}

trap cleanup EXIT

echo ">>> Setting up E2E test database..."

# Create and populate test database
DATABASE_URL="$TEST_DB_URL" php "$PROJECT_DIR/bin/console" doctrine:database:create --if-not-exists 2>/dev/null || true
DATABASE_URL="$TEST_DB_URL" php "$PROJECT_DIR/bin/console" doctrine:schema:update --force --complete
DATABASE_URL="$TEST_DB_URL" php "$PROJECT_DIR/bin/console" doctrine:fixtures:load --no-interaction

echo ">>> Switching app to test database..."

# Backup existing .env.local if present
if [ -f "$ENV_LOCAL" ]; then
    cp "$ENV_LOCAL" "$ENV_LOCAL_BACKUP"
fi

# Write test DATABASE_URL to .env.local (overrides .env)
if [ -f "$ENV_LOCAL" ]; then
    # Remove any existing DATABASE_URL line and append the test one
    grep -v '^DATABASE_URL=' "$ENV_LOCAL" > "$ENV_LOCAL.tmp" || true
    echo "DATABASE_URL=\"$TEST_DB_URL\"" >> "$ENV_LOCAL.tmp"
    mv "$ENV_LOCAL.tmp" "$ENV_LOCAL"
else
    echo "DATABASE_URL=\"$TEST_DB_URL\"" > "$ENV_LOCAL"
fi

# Clear cache so Symfony picks up the new DATABASE_URL
php "$PROJECT_DIR/bin/console" cache:clear --env=dev --quiet

echo ">>> Running Playwright tests..."

cd "$PROJECT_DIR"
npx playwright test "$@"

echo ">>> E2E tests completed successfully!"
