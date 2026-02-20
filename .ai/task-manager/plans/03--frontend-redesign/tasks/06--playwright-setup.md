---
id: 6
group: "dev-tooling"
dependencies: []
status: "completed"
created: "2026-02-19"
skills: ["playwright-cli"]
---
# Set Up Playwright for Browser Verification

## Objective
Configure the `playwright-cli` skill for verifying frontend changes via automated browser interactions. No npm setup, `package.json`, or devDependencies are needed — the skill manages browser infrastructure internally.

## Skills Required
- `playwright-cli`: Browser automation for navigating pages, taking screenshots, clicking elements, and extracting data

## Acceptance Criteria
- [ ] `playwright-cli` skill can navigate to `https://strava.ddev.site/activities` and take a screenshot
- [ ] `playwright-cli` skill can interact with page elements (click, filter, navigate)
- [ ] No `package.json`, `package-lock.json`, or `node_modules/` are added to the project for Playwright

Use your internal Todo tool to track these and keep on track.

## Technical Requirements
- Use the `playwright-cli` skill for all browser verification
- No npm packages or project-level dependencies needed
- Not part of the build pipeline, CI, or deployment

## Input Dependencies
None — this is independent of the PHP/Symfony stack.

## Output Artifacts
- Verified `playwright-cli` skill works with the DDEV site

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### Step 1: Verify playwright-cli skill availability
Invoke the `playwright-cli` skill to confirm it is available and working.

### Step 2: Navigate to the site
Use `playwright-cli` to navigate to `https://strava.ddev.site/activities` and take a screenshot to verify the page loads.

### Step 3: Test interactions
Use `playwright-cli` to click elements, verify Turbo Frame navigation, and test filter dropdowns on the calendar page.

</details>
