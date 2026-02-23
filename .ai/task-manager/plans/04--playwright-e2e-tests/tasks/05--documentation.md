---
id: 5
group: "documentation"
dependencies: [2, 3, 4]
status: "pending"
created: "2026-02-23"
skills: ["documentation"]
---
# Update Documentation and Verify Full Test Suite

## Objective
Update CLAUDE.md with E2E test commands and conventions, run the full Playwright test suite to verify all tests pass, and ensure linting passes.

## Skills Required
- `documentation`: Update project documentation with accurate commands and conventions

## Acceptance Criteria
- [ ] CLAUDE.md updated with Playwright test run commands (individual and full suite)
- [ ] CLAUDE.md updated with fixture loading commands
- [ ] CLAUDE.md updated with test database setup notes
- [ ] CLAUDE.md mentions E2E test file organization in project structure section
- [ ] Full E2E test suite passes: `npx playwright test`
- [ ] PHPStan level 8 passes
- [ ] PHP-CS-Fixer passes
- [ ] `.gitignore` updated to exclude Playwright artifacts (test-results/, playwright-report/)

Use your internal Todo tool to track these and keep on track.

## Technical Requirements
- Update existing sections in CLAUDE.md rather than adding redundant new sections
- Add `node_modules/` to `.gitignore` if not already present
- Add Playwright output directories to `.gitignore`
- Ensure `package.json` scripts section has a `test:e2e` script

## Input Dependencies
- Tasks 02, 03, 04 completed: all E2E test files written and passing individually

## Output Artifacts
- Updated `CLAUDE.md`
- Updated `.gitignore`
- Verified full test suite green

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### CLAUDE.md updates

Add to the "Run tests" section:
```bash
npx playwright test                                    # Run all E2E tests
npx playwright test tests/e2e/calendar.spec.ts         # Run calendar tests only
npx playwright test --headed                           # Run with visible browser
```

Add to the "Local Development" section:
```bash
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:schema:update --env=test --force
php bin/console doctrine:fixtures:load --env=test --no-interaction
```

Update "Project Structure" to include:
```
tests/
├── e2e/            # Playwright E2E tests
│   ├── calendar.spec.ts
│   ├── patterns.spec.ts
│   └── activity-detail.spec.ts
└── Pattern/        # PHPUnit tests
```

### .gitignore additions
```
node_modules/
test-results/
playwright-report/
```

</details>
