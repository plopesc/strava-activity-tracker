---
id: 6
group: "web-dashboard"
dependencies: [2, 5]
status: "in-progress"
created: 2026-02-18
skills:
  - php
  - twig
---
# Activity List and Pattern Group Views

## Objective
Build the two primary web views: the activity list page (all synced activities grouped by pattern signature) and the pattern group page (all activities sharing a specific pattern, with a trend line). Both are server-rendered Twig pages with Symfony controllers.

## Skills Required
- Symfony controller / routing
- Twig templating

## Acceptance Criteria
- [ ] `GET /` redirects to `GET /activities`
- [ ] `GET /activities` renders a page grouping all activities by their `patternSignature` (activities without a pattern appear under an "Unclassified" group)
- [ ] Each group header shows the pattern signature and a count of matching activities
- [ ] Each activity row shows: date, name, total distance (km), average pace (min/km), average heart rate (if available)
- [ ] `GET /activities/pattern/{signature}` renders a page listing only activities with that signature, ordered by date ascending
- [ ] The pattern group page includes a small trend summary: the change in average pace from the oldest to the newest activity in the group (displayed as text, e.g., "↑ improved 0:12 min/km over 8 sessions")
- [ ] Each activity row on the pattern group page has a checkbox (for multi-select comparison) and a link to the comparison view
- [ ] A "Compare selected" button on the pattern group page submits selected activity IDs to `GET /activities/compare?ids[]=…`
- [ ] Pages extend `base.html.twig` and are readable without CSS (functional HTML-first)

## Technical Requirements
- Symfony routing via PHP attributes (`#[Route]`)
- Doctrine `ActivityRepository` queries
- Twig templates

## Input Dependencies
- Task 02: `Activity` entity + `ActivityRepository::findByPatternSignature()`
- Task 05: Synced activities must exist for meaningful output (functional dependency)

## Output Artifacts
- `src/Controller/ActivityController.php` (two actions)
- `templates/activity/index.html.twig`
- `templates/activity/pattern_group.html.twig`

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

1. **ActivityRepository additions** (add to existing repository from Task 02):
   - `findGroupedByPattern(): array` — returns all activities ordered by `patternSignature ASC, activityDate DESC`. Group them in PHP by `patternSignature`. Null signatures go into a `null` key.
   - `findByPatternSignature(string $sig): array` — returns activities where `patternSignature = :sig`, ordered by `activityDate ASC`.

2. **Controller**:
   ```php
   #[Route('/activities', name: 'activity_list')]
   public function list(): Response { ... }

   #[Route('/activities/pattern/{signature}', name: 'activity_pattern_group')]
   public function patternGroup(string $signature): Response { ... }
   ```
   Root route (`GET /`) can be a simple redirect controller method using `#[Route('/')]`.

3. **Grouping in the list view**: Pass the grouped array to Twig. In `index.html.twig`, iterate over groups; render the "Unclassified" group last if present.

4. **Pace formatting helper**: Create a Twig extension or a simple static helper that converts m/s to min/km string (`mm:ss`). Register it as a Twig filter (`pace_format`). Example: 3.5 m/s → `4:46 min/km`.

5. **Trend calculation in pattern group**: In the controller, take the `activityDate`-sorted list, compare `averageSpeed` of first vs last activity. Compute delta in pace (min/km). Pass a `$trendText` string to the template.

6. **Multi-select form**: In `pattern_group.html.twig`, wrap the activity list in a `<form method="GET" action="{{ path('activity_compare') }}">`. Each row has `<input type="checkbox" name="ids[]" value="{{ activity.id }}">`. The submit button sends the form. Require a minimum of 2 selected (add `data-min-select="2"` and a small inline `<script>` that validates before submit).

7. **URL encoding**: The `{signature}` route parameter may contain spaces and special characters. URL-encode when generating links (`{{ path('activity_pattern_group', {signature: sig})|url_encode }}`... or just pass the encoded value from the controller). Alternatively use the activity's `patternType` as a route parameter and the signature as a query param.

</details>
