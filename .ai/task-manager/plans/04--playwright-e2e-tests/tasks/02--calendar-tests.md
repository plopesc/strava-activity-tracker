---
id: 2
group: "e2e-tests"
dependencies: [1]
status: "pending"
created: "2026-02-23"
skills: ["playwright"]
---
# Implement Calendar Page E2E Tests

## Objective
Write Playwright tests covering the calendar page: month navigation, future month guard, activity dot rendering, pattern and gear filters, clear filters, and sidebar card loading via Turbo Frame.

## Skills Required
- `playwright`: Browser automation, element selectors, Turbo Frame waiting, assertion patterns

## Acceptance Criteria
- [ ] Tests navigate between months using "Previous month" / "Next month" controls and verify the heading changes
- [ ] Tests verify that the "Next month" control is disabled (rendered as `<span>` with `aria-disabled="true"`) when viewing the current month
- [ ] Tests verify that "Next month" is an active `<a>` link when viewing a past month
- [ ] Tests navigate to a known month with fixture activities and verify activity dots appear in the calendar grid with distance labels
- [ ] Tests select a pattern from `#filter-pattern` and verify the calendar updates to show only matching activities
- [ ] Tests select a gear from `#filter-gear` and verify filtering works
- [ ] Tests verify "Clear filters" link appears when filters are active and removes filters when clicked
- [ ] Tests click an activity dot and verify the sidebar `turbo-frame#activity-detail` loads with activity details (name, date, stats, pattern badge)
- [ ] Tests verify clicking a different dot updates the sidebar content
- [ ] All tests pass via `npx playwright test tests/e2e/calendar.spec.ts`

Use your internal Todo tool to track these and keep on track.

## Technical Requirements
- Test file: `tests/e2e/calendar.spec.ts`
- Use fixture data dates to navigate to specific months (e.g., `/activities?year=2025&month=11`)
- Wait for Turbo Frame updates using `page.waitForSelector` or `frameLocator` patterns for `turbo-frame#calendar-grid` and `turbo-frame#activity-detail`
- Use `aria-label` selectors for navigation controls: `[aria-label="Previous month"]`, `[aria-label="Next month"]`
- For filter selects: `#filter-pattern`, `#filter-gear`
- Activity dots are `<a>` links within the grid with `data-turbo-frame="activity-detail"`
- Sidebar card content includes: activity name in `<h3>`, pattern type badge `<span>`, stats in `<dl>`
- "Clear filters" is a text link

## Input Dependencies
- Task 01 completed: Playwright installed, test DB with fixtures loaded, smoke test passing

## Output Artifacts
- `tests/e2e/calendar.spec.ts` with all calendar page tests

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### Test structure suggestion

```
describe('Calendar Page')
  test('navigates to previous and next month')
  test('disables next month on current month')
  test('enables next month on past months')
  test('displays activity dots for months with data')
  test('filters by pattern')
  test('filters by gear')
  test('clears filters')
  test('loads activity card in sidebar on dot click')
  test('updates sidebar when clicking different activity')
```

### Key selectors
- Month heading: `h2` containing month/year text (e.g., "November 2025")
- Navigation: `a[aria-label="Previous month"]`, `a[aria-label="Next month"]` or `span[aria-disabled="true"]`
- Filter form: `select#filter-pattern`, `select#filter-gear`
- Calendar grid: `.grid.grid-cols-7` (desktop view)
- Activity dots: links inside grid cells with `data-turbo-frame="activity-detail"`
- Sidebar frame: `turbo-frame#activity-detail`
- Clear filters: link with text "Clear filters"

### Turbo Frame navigation
After clicking navigation links or filter changes, wait for the `turbo-frame#calendar-grid` to update. Playwright's `waitForSelector` or checking for updated text content works well here.

### Future month guard
Navigate to `/activities` (defaults to current month Feb 2026). Check that the "Next month" element is a `span[aria-disabled="true"]`, not an `<a>`. Then navigate to `/activities?year=2025&month=11` and verify "Next month" is an `<a>`.

</details>
