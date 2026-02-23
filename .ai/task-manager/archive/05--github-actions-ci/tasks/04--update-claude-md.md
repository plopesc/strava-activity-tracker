---
id: 4
group: "github-actions-ci"
dependencies: [1, 2, 3]
status: "completed"
created: "2026-02-23"
skills:
  - documentation
---
# Update CLAUDE.md with CI Section

## Objective

Add a "CI" section to `CLAUDE.md` that documents the three GitHub Actions workflows, their triggers, and how to interpret failures — including where to find the Playwright HTML report artifact on the GitHub Actions run summary.

## Skills Required

- documentation: writing clear, developer-facing markdown documentation consistent with the existing CLAUDE.md style

## Acceptance Criteria

- [ ] `CLAUDE.md` contains a new `## CI` section (or appropriately named heading matching the document's existing hierarchy)
- [ ] The section names all three workflow files: `code-quality.yml`, `phpunit.yml`, `e2e.yml`
- [ ] The section documents the trigger: push and pull_request to `main`
- [ ] The section explains what each workflow checks (code quality, unit tests, E2E tests)
- [ ] The section explains where to find the Playwright HTML report artifact when the E2E workflow fails (GitHub Actions run summary → Artifacts section)
- [ ] The section is concise and consistent with the existing CLAUDE.md writing style (command-focused, no fluff)
- [ ] No other sections of `CLAUDE.md` are modified

## Technical Requirements

- Read the current `CLAUDE.md` to understand the document structure and style before writing
- Place the new `## CI` section logically near the testing sections (after `## Run tests` / `## E2E tests` commands, or at a logical grouping point)
- The Playwright artifact note should state: on E2E failure, download the `playwright-report` artifact from the GitHub Actions run summary page for screenshots, traces, and error details

## Input Dependencies

- Task 1: `.github/workflows/code-quality.yml` — needed to document the actual workflow filename and what it checks
- Task 2: `.github/workflows/phpunit.yml` — needed to document the actual workflow filename and what it checks
- Task 3: `.github/workflows/e2e.yml` — needed to document the actual workflow filename and the artifact name

## Output Artifacts

- Updated `CLAUDE.md` with a new CI section

## Implementation Notes

<details>
<summary>Documentation guidance</summary>

1. Read `/var/www/html/CLAUDE.md` first to understand the exact heading hierarchy and code block style.

2. The new section should follow the same pattern as the existing `## Run tests` block: a brief paragraph or bullet list, with command examples in fenced code blocks where relevant.

3. Example structure (adapt to match the actual document style):

   ```markdown
   ## CI (GitHub Actions)

   Three workflows run automatically on push and pull request to `main`:

   | Workflow | File | What it checks |
   |---|---|---|
   | Code Quality | `.github/workflows/code-quality.yml` | PHPStan level 8 + PHP-CS-Fixer formatting |
   | PHPUnit | `.github/workflows/phpunit.yml` | Full unit test suite against MariaDB 11.8 |
   | E2E Tests | `.github/workflows/e2e.yml` | Playwright browser tests via DDEV |

   **On E2E failure:** Download the `playwright-report` artifact from the GitHub Actions run
   summary (Actions tab → failed run → Artifacts section) to view screenshots, traces, and
   error details. Artifacts are retained for 7 days.
   ```

4. Do not add time estimates, phase descriptions, or implementation details — keep the section operator-focused (what triggers it, what passes/fails, where to look when things go wrong).

5. Insert the section after the existing E2E test documentation block to keep all testing-related content together.
</details>
