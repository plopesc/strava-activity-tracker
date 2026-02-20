---
id: 2
group: "calendar"
dependencies: [1]
status: "completed"
created: "2026-02-19"
skills: ["php", "twig"]
complexity_score: 5
complexity_notes: "New controller action, repository method, complex Twig template with calendar grid, Turbo Frames for filters and month navigation, responsive mobile layout."
---
# Build Calendar Page and Controller

## Objective
Create the new default view at `/activities` showing a monthly calendar grid with color-coded activity icons, dropdown filters (pattern signature, gear), and Turbo Frame-based month navigation. On mobile, the calendar collapses to a chronological activity list.

## Skills Required
- `php`: Controller action with query parameters, repository method with date-range and filter queries, Doctrine QueryBuilder
- `twig`: Calendar grid template with CSS Grid, Turbo Frame integration, responsive layout with Tailwind

## Acceptance Criteria
- [ ] `/activities` renders a monthly calendar grid (7-column, Monday–Sunday) for the current month by default
- [ ] Query parameters `month`, `year`, `pattern`, `gear` filter the displayed activities
- [ ] Each day cell shows color-coded icons for activities on that day, colored by `patternType` (steady=blue, intervals=orange, tempo=red, long_run=green, unclassified=gray)
- [ ] Each activity icon is a link targeting the `activity-detail` Turbo Frame (from Task 3)
- [ ] Prev/next month arrows navigate months via Turbo Frame (`calendar-grid`) without full page reload
- [ ] Pattern signature and gear dropdown filters submit to the same route, targeting the `calendar-grid` Turbo Frame
- [ ] On viewports below Tailwind `md` breakpoint, the calendar collapses to a chronological list of activities
- [ ] A new `ActivityRepository` method retrieves activities for a given year/month with optional pattern and gear filters, eagerly loading the Gear relation
- [ ] Days with more than 3 activities show up to 3 icons plus a "+N more" indicator
- [ ] The `HomeController` or existing `/` route redirects to `/activities`
- [ ] PHPStan level 8 and PHP-CS-Fixer pass

Use your internal Todo tool to track these and keep on track.

## Technical Requirements
- Symfony controller action on `ActivityController` replacing the current `list` action's route
- Doctrine QueryBuilder with `BETWEEN` date filtering for year/month, optional `WHERE` for pattern signature and gear
- Eager loading of Gear relation (`->leftJoin('a.gear', 'g')->addSelect('g')`)
- Turbo Frame `calendar-grid` wrapping the calendar grid and filter dropdowns
- Turbo Frame `activity-detail` placeholder (empty sidebar, populated by Task 3)
- CSS Grid for the 7-column calendar layout
- Tailwind responsive: `md:grid` for desktop calendar, list layout for mobile

## Input Dependencies
- Task 1: AssetMapper, Tailwind, Turbo, and Stimulus must be configured; base template with navigation must exist

## Output Artifacts
- Updated `ActivityController` with `calendar` action at `/activities`
- New `ActivityRepository::findByMonth(int $year, int $month, ?string $pattern, ?string $gear): array` method
- New template `templates/activity/calendar.html.twig`
- Updated `/` redirect to point to `/activities`
- Desktop layout: left sidebar (`activity-detail` frame placeholder) + right content area (calendar grid)

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### Step 1: Repository Method
Add to `ActivityRepository`:
```php
/**
 * @return Activity[]
 */
public function findByMonth(int $year, int $month, ?string $patternSignature = null, ?string $gear = null): array
{
    $start = new \DateTimeImmutable("$year-$month-01");
    $end = $start->modify('last day of this month')->setTime(23, 59, 59);

    $qb = $this->createQueryBuilder('a')
        ->leftJoin('a.gear', 'g')
        ->addSelect('g')
        ->where('a.activityDate BETWEEN :start AND :end')
        ->setParameter('start', $start)
        ->setParameter('end', $end)
        ->orderBy('a.activityDate', 'ASC');

    if ($patternSignature !== null) {
        $qb->andWhere('a.patternSignature = :pattern')
           ->setParameter('pattern', $patternSignature);
    }

    if ($gear !== null) {
        $qb->andWhere('g.name = :gear')
           ->setParameter('gear', $gear);
    }

    return $qb->getQuery()->getResult();
}
```

Also add methods to get distinct pattern signatures and gear names for filter dropdowns:
```php
public function findDistinctPatternSignatures(): array
public function findDistinctGearNames(): array
```

### Step 2: Controller Action
In `ActivityController`, create (or update) the calendar action:
```php
#[Route('/activities', name: 'activity_calendar')]
public function calendar(Request $request, ActivityRepository $repo): Response
{
    $year = $request->query->getInt('year', (int) date('Y'));
    $month = $request->query->getInt('month', (int) date('n'));

    // Clamp month and adjust year
    if ($month < 1) { $month = 12; $year--; }
    if ($month > 12) { $month = 1; $year++; }

    $pattern = $request->query->get('pattern');
    $gear = $request->query->get('gear');

    $activities = $repo->findByMonth($year, $month, $pattern, $gear);
    $activitiesByDay = [];
    foreach ($activities as $activity) {
        $day = (int) $activity->getActivityDate()->format('j');
        $activitiesByDay[$day][] = $activity;
    }

    return $this->render('activity/calendar.html.twig', [
        'year' => $year,
        'month' => $month,
        'activitiesByDay' => $activitiesByDay,
        'patterns' => $repo->findDistinctPatternSignatures(),
        'gears' => $repo->findDistinctGearNames(),
        'selectedPattern' => $pattern,
        'selectedGear' => $gear,
    ]);
}
```

If the current `list` action is at `/activities`, move it to `/activities/pattern` (coordinate with Task 4).

### Step 3: Calendar Template
Create `templates/activity/calendar.html.twig`:

Key layout structure:
```twig
{% extends 'base.html.twig' %}

{% block body %}
<div class="flex flex-col md:flex-row gap-4 p-4">
    {# Detail sidebar - placeholder for Turbo Frame #}
    <div class="w-full md:w-80 shrink-0">
        <turbo-frame id="activity-detail">
            <div class="bg-gray-50 rounded-lg p-4 text-gray-500 text-center">
                Click an activity to see details
            </div>
        </turbo-frame>
    </div>

    {# Calendar area #}
    <div class="flex-1">
        <turbo-frame id="calendar-grid">
            {# Month header with prev/next arrows #}
            {# Filter dropdowns #}
            {# Desktop: 7-column grid #}
            {# Mobile: chronological list #}
        </turbo-frame>
    </div>
</div>
{% endblock %}
```

For the calendar grid, compute the first day of the month's weekday (Monday=1) and render empty cells before day 1. Use `{% for day in 1..daysInMonth %}` to render each day cell.

Pattern type color mapping in Twig (define as a macro or set variable):
```twig
{% set patternColors = {
    'steady': 'bg-pattern-steady',
    'intervals': 'bg-pattern-intervals',
    'tempo': 'bg-pattern-tempo',
    'long_run': 'bg-pattern-long-run',
} %}
```

Each activity icon links to the detail frame:
```twig
<a href="{{ path('activity_detail', {id: activity.id}) }}"
   data-turbo-frame="activity-detail"
   class="w-3 h-3 rounded-full {{ patternColors[activity.patternType] ?? 'bg-pattern-unclassified' }}"
   title="{{ activity.name }}">
</a>
```

For "+N more" when >3 activities on a day:
```twig
{% if activitiesByDay[day]|length > 3 %}
    <span class="text-xs text-gray-400">+{{ activitiesByDay[day]|length - 3 }} more</span>
{% endif %}
```

Month navigation arrows target the calendar-grid frame:
```twig
<a href="{{ path('activity_calendar', {year: prevYear, month: prevMonth, pattern: selectedPattern, gear: selectedGear}) }}"
   data-turbo-frame="calendar-grid">
    ← Prev
</a>
```

Mobile responsive: use `hidden md:grid` for the calendar grid and `md:hidden` for the list view.

### Step 4: Update home redirect
Ensure `/` redirects to `/activities` (the calendar). Check the existing home route and update if needed.

</details>
