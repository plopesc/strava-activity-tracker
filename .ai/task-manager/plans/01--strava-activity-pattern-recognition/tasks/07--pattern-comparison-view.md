---
id: 7
group: "web-dashboard"
dependencies: [6]
status: "in-progress"
created: 2026-02-18
skills:
  - php
  - twig
---
# Pattern Comparison View

## Objective
Build the activity comparison page that accepts two or more activity IDs via query string and renders four comparison panels: segment paces (bar chart), heart rate per segment (bar chart), overall statistics (table), and performance trend over time (line chart). Charts are rendered client-side using Chart.js.

## Skills Required
- Symfony controller / data assembly
- Twig templating + Chart.js

## Acceptance Criteria
- [ ] `GET /activities/compare?ids[]=1&ids[]=2` (and optionally more IDs) renders the comparison page
- [ ] A minimum of 2 and a maximum of 5 activity IDs are accepted; out-of-range requests redirect back with a flash error
- [ ] All selected activities must share the same `patternSignature`; mismatched selections redirect with an error
- [ ] **Panel 1 — Segment paces**: A Chart.js grouped bar chart showing pace (min/km) per segment for each selected activity; x-axis labels are segment labels from the signature (e.g., "warmup", "fast ×3"), one dataset per activity coloured distinctly
- [ ] **Panel 2 — Heart rate per segment**: A Chart.js grouped bar chart showing average HR per segment per activity; if any selected activity has no HR stream data the panel is omitted and a note is displayed
- [ ] **Panel 3 — Overall stats**: An HTML table with columns: Activity date, Name, Total distance (km), Elapsed time (mm:ss), Average pace (min/km), Average HR (or "—")
- [ ] **Panel 4 — Progress over time**: A Chart.js line chart plotting average pace (y-axis) vs activity date (x-axis) for ALL activities that share the same pattern (not just the selected ones), with the selected activities highlighted as larger points
- [ ] The page includes a "← Back to pattern group" link
- [ ] Charts render correctly with Chart.js loaded from CDN in `base.html.twig`

## Technical Requirements
- Symfony controller with `#[Route]`
- Doctrine `ActivityRepository`
- Chart.js (CDN, via inline `<script>` blocks in the Twig template)
- `PatternRecognizer::haveSamePattern()` for validation of the selection

## Input Dependencies
- Task 06: `ActivityController` structure, Twig layout, pace formatting Twig filter
- Task 04: `PatternRecognizer::haveSamePattern()` for input validation

## Output Artifacts
- `src/Controller/ComparisonController.php`
- `templates/activity/comparison.html.twig`

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

1. **Route**:
   ```php
   #[Route('/activities/compare', name: 'activity_compare')]
   public function compare(Request $request): Response
   ```
   Read `$ids = $request->query->all('ids')` → array of ints.

2. **Validation**:
   - If `count($ids) < 2` or `> 5`: add flash error and redirect to referrer or `/activities`.
   - Load all activities from DB: `$repo->findBy(['id' => $ids])`. If count doesn't match (some IDs invalid): redirect with error.
   - Verify all have the same `patternSignature`: compare `array_unique(array_column($activities, 'patternSignature'))`. If count > 1: redirect with error.

3. **Segment pace data assembly**:
   - Decode `patternSegments` JSON for each activity.
   - For each segment position, compute pace from the segment's portion of the lap/stream data.
   - Since `patternSegments` stores distances but not per-segment speed directly, derive it: for lap-based activities iterate `rawLaps` matching to segments; for stream-based, compute average speed from the matching stream slice.
   - **Simpler approach**: Store per-segment average speed directly in `patternSegments` during classification (add `avg_speed_ms` field per segment in Task 04's output). This avoids re-processing here.
   - Pass `$segmentLabels` (x-axis) and `$datasetsForPace` (one per activity) to Twig as JSON-encoded arrays.

4. **HR data assembly**: Same approach — store per-segment average HR in `patternSegments` during classification when HR stream data is available. If any selected activity has `null` HR segments, set `$hrAvailable = false`.

5. **Overall stats table**: Straightforward mapping from entity fields. Format elapsed time as `gmdate('H:i:s', $elapsedTime)` or `mm:ss` for sub-hour activities.

6. **Trend line data**: Load ALL activities with the same `patternSignature` sorted by `activityDate ASC`. Build a dataset of `(activityDate, averageSpeed)` pairs. Pass as a JSON array. In the Twig `<script>` block, mark selected activity IDs with a larger point radius.

7. **Chart.js in Twig**: In `comparison.html.twig`, use Twig `{% block scripts %}{% endblock %}` extending `base.html.twig`. Build the Chart.js config objects inline in `<script>` tags using `{{ data|json_encode|raw }}` to pass PHP arrays as JS objects. Use distinct colours for activities (a static array of 5 colours is sufficient).

8. **Segment label note**: For `short_run`/`long_run`, the segment labels are not meaningful (one whole-activity segment). The pace bar chart still makes sense (one bar per activity), showing whole-activity pace. HR panel shows whole-activity average HR.

</details>
