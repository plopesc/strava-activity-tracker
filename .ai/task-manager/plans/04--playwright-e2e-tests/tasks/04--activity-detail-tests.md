---
id: 4
group: "e2e-tests"
dependencies: [1]
status: "pending"
created: "2026-02-23"
skills: ["playwright"]
---
# Implement Activity Detail Page E2E Tests

## Objective
Write Playwright tests covering the activity detail page: verify map and charts render when stream data is available, and graceful degradation when data is missing.

## Skills Required
- `playwright`: Browser automation, element presence/absence assertions, canvas element verification

## Acceptance Criteria
- [ ] Tests verify an activity with full stream data (latlng + velocity_smooth + heartrate) shows `#activityMap`, `#paceChart`, and `#hrChart`
- [ ] Tests verify the Leaflet map container has tile elements loaded
- [ ] Tests verify an activity with partial streams (velocity_smooth only, no heartrate) shows `#paceChart` but NOT `#hrChart`
- [ ] Tests verify an activity with partial streams but no latlng shows charts but NOT the map
- [ ] Tests verify an activity with no stream data shows "No stream data available for this activity." text and no map
- [ ] Tests verify the activity card shows correct stats (name, date, distance, pace, pattern badge)
- [ ] Tests verify null fields display em-dash ("—") in the card
- [ ] Tests verify the Strava external link points to the correct URL using the activity's stravaId
- [ ] All tests pass via `npx playwright test tests/e2e/activity-detail.spec.ts`

Use your internal Todo tool to track these and keep on track.

## Technical Requirements
- Test file: `tests/e2e/activity-detail.spec.ts`
- Activity detail URL: `/activities/{id}/detail` where `{id}` is the database PK
- Map element: `#activityMap` inside a container div; Leaflet adds `.leaflet-container` class and `.leaflet-tile-pane` for tiles
- Pace chart: `canvas#paceChart`
- HR chart: `canvas#hrChart`
- No-data text: "No stream data available for this activity."
- Card: includes `<h3>` with activity name, `<dl>` with stats, pattern badge `<span>`
- Strava link: `a[href^="https://www.strava.com/activities/"]`
- The fixture data must include activities with known IDs for each scenario (full data, partial, no data)

## Input Dependencies
- Task 01 completed: Playwright installed, test DB with fixtures loaded
- Fixture activities must include at least one of each: full streams, partial streams (no HR), partial streams (no latlng), no streams

## Output Artifacts
- `tests/e2e/activity-detail.spec.ts` with all activity detail page tests

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### Test structure suggestion

```
describe('Activity Detail Page')
  test('shows map, pace chart, and HR chart for full-data activity')
  test('shows Leaflet map with tiles')
  test('shows pace chart but not HR chart when heartrate missing')
  test('shows charts but no map when latlng missing')
  test('shows no-data message when no streams')
  test('displays activity card with correct stats')
  test('shows em-dash for null fields')
  test('has correct Strava external link')
```

### Finding fixture activity IDs
The test needs to know which activity IDs correspond to which data scenarios. Options:
1. Query the fixture data by name pattern (e.g., navigate to pattern list, find the activity, extract the ID from the link)
2. Use the API/page to discover IDs dynamically
3. Hard-code IDs if fixtures always produce the same auto-increment sequence (fragile but simple)

**Recommended approach**: Navigate to `/activities/pattern` first, find activities by name, extract IDs from their detail links. Or use a `beforeAll` hook to discover IDs.

Alternatively, use meaningful activity names in fixtures (e.g., "Full Data Run", "No HR Run", "No Streams Run") and search for them.

### Map verification
- Check `#activityMap` exists
- Check `.leaflet-container` class is applied (Leaflet initialized)
- Check `.leaflet-tile-pane` has child elements (tiles loading)
- Don't wait for all tiles — external CDN may be slow

### Chart verification
- Check `canvas#paceChart` exists and has non-zero dimensions
- Check `canvas#hrChart` exists or doesn't exist based on scenario
- Chart.js renders to canvas — can't inspect drawn content, but can verify element presence

### Graceful degradation
- No map: `#activityMap` should NOT be in the DOM (the entire map section is conditionally rendered)
- No charts: text "No stream data available for this activity." should be visible
- No HR chart only: `#paceChart` present, `#hrChart` absent, no "no data" message

</details>
