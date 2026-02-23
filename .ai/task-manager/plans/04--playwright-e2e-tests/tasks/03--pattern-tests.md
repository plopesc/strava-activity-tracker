---
id: 3
group: "e2e-tests"
dependencies: [1]
status: "pending"
created: "2026-02-23"
skills: ["playwright"]
---
# Implement Pattern Pages E2E Tests

## Objective
Write Playwright tests covering the pattern list page and pattern detail page: verify pattern groups are listed with accurate data, client-side sorting works, and pattern detail shows paginated sortable tables.

## Skills Required
- `playwright`: Browser automation, table content assertions, client-side sorting verification

## Acceptance Criteria
- [ ] Tests verify all expected pattern groups appear on `/activities/pattern` with correct activity counts
- [ ] Tests verify activity data (names, dates, distances, paces) is displayed in sortable tables within each group
- [ ] Tests verify client-side sorting by clicking a column header and checking row reorder
- [ ] Tests click a pattern signature link and verify navigation to the pattern detail page
- [ ] Tests verify the pattern detail page shows the correct heading and activity count
- [ ] Tests verify sort links on pattern detail work (server-side via Turbo Frame)
- [ ] Tests verify unclassified activities group is shown appropriately
- [ ] All tests pass via `npx playwright test tests/e2e/patterns.spec.ts`

Use your internal Todo tool to track these and keep on track.

## Technical Requirements
- Test file: `tests/e2e/patterns.spec.ts`
- Pattern list URL: `/activities/pattern`
- Pattern detail URL: `/activities/pattern/{signature}`
- Pattern list uses `data-controller="sortable-table"` with `data-action="click->sortable-table#sort"` on headers
- Sort headers have `data-col="0"` through `data-col="4"` attributes
- Table body: `[data-sortable-table-target="body"]`
- Cells use `data-sort-value` for numeric comparison
- Pattern detail uses Turbo Frame `turbo-frame#pattern-table` for server-side sort and pagination
- Sort links on detail page include `sort` and `direction` query params
- Activity name links navigate to `activity_detail`

## Input Dependencies
- Task 01 completed: Playwright installed, test DB with fixtures loaded

## Output Artifacts
- `tests/e2e/patterns.spec.ts` with all pattern page tests

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### Test structure suggestion

```
describe('Pattern List Page')
  test('displays all pattern groups with activity counts')
  test('shows activity data in tables')
  test('client-side sorts by column')
  test('navigates to pattern detail on signature click')
  test('shows unclassified group')

describe('Pattern Detail Page')
  test('displays pattern heading and count')
  test('sorts by column via server-side Turbo Frame')
  test('activity links navigate to detail page')
```

### Key selectors
- Page heading: `h1` with text "Activity Patterns"
- Pattern group cards: look for signature text and "N activities" badge
- Sortable headers: elements with `data-action="click->sortable-table#sort"`
- Table rows: `tr` within `[data-sortable-table-target="body"]`
- Pattern detail heading: `h1` with signature text
- Pattern detail Turbo Frame: `turbo-frame#pattern-table`
- Sort direction indicators: arrow characters in column headers

### Client-side sort verification
1. Read values from a column (e.g., distance) before sorting
2. Click the column header
3. Read values again and verify they are in ascending order
4. Click again and verify descending order

### Unclassified group
Unclassified activities appear with "Unclassified" text (not a link) in the pattern list.

</details>
