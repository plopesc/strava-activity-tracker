---
id: 5
group: "pattern-pages"
dependencies: [1]
status: "pending"
created: "2026-02-19"
skills: ["php", "twig"]
complexity_score: 5
complexity_notes: "Server-side pagination and sorting with Turbo Frame, preserving existing comparison checkbox feature, multiple sort columns."
---
# Enhance Pattern Detail Page with Pagination and Sorting

## Objective
Update the pattern detail view at `/activities/pattern/{signature}` with server-side pagination (25/page), sortable columns via query parameters, Turbo Frame partial updates, and a gear column. Preserve the existing comparison checkbox feature.

## Skills Required
- `php`: Server-side pagination with Doctrine setFirstResult/setMaxResults, multi-column sorting, query parameters
- `twig`: Sortable column headers as links, pagination controls, Turbo Frame wrapping, comparison checkboxes with Stimulus

## Acceptance Criteria
- [ ] `/activities/pattern/{signature}` accepts `page`, `sort`, and `direction` query parameters
- [ ] Server-side pagination at 25 items per page using Doctrine
- [ ] All table columns are sortable: date, name, distance, pace, duration, avg HR, gear
- [ ] Column headers render as clickable links that toggle sort direction (ASC/DESC)
- [ ] Pagination controls (prev/next/page numbers) are displayed
- [ ] Table and pagination are wrapped in a Turbo Frame (`pattern-table`) for partial updates
- [ ] Gear column displays gear name or "—" if null
- [ ] Comparison checkbox feature is preserved: selected activity IDs are tracked and submitted to `/activities/compare`
- [ ] A Stimulus controller manages comparison checkbox state across paginated pages
- [ ] Trend text computation remains unchanged
- [ ] PHPStan level 8 and PHP-CS-Fixer pass

Use your internal Todo tool to track these and keep on track.

## Technical Requirements
- Updated `patternGroup` action (or equivalent) on `ActivityController`
- Repository method with pagination and dynamic sort column/direction
- Turbo Frame `pattern-table` wrapping the table and pagination controls
- Sort/pagination links target the `pattern-table` frame
- Stimulus controller for comparison checkbox tracking across page navigations
- Alternating row backgrounds, hover states, compact padding (Tailwind)

## Input Dependencies
- Task 1: AssetMapper, Tailwind, Turbo, Stimulus, and base template must be configured

## Output Artifacts
- Updated controller action with pagination and sorting
- Updated or new repository method with pagination support
- `templates/activity/pattern_detail.html.twig` template
- Stimulus controller for comparison checkboxes `assets/controllers/comparison-selector_controller.js`

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### Step 1: Repository Method
Add a paginated query method to `ActivityRepository`:
```php
/**
 * @return array{activities: Activity[], total: int}
 */
public function findByPatternSignaturePaginated(
    string $signature,
    int $page = 1,
    int $limit = 25,
    string $sort = 'activityDate',
    string $direction = 'DESC'
): array {
    // Whitelist allowed sort columns
    $allowedSorts = [
        'date' => 'a.activityDate',
        'name' => 'a.name',
        'distance' => 'a.distance',
        'pace' => 'a.averageSpeed',
        'duration' => 'a.elapsedTime',
        'hr' => 'a.averageHeartrate',
        'gear' => 'g.name',
    ];

    $sortColumn = $allowedSorts[$sort] ?? 'a.activityDate';
    $sortDir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

    $qb = $this->createQueryBuilder('a')
        ->leftJoin('a.gear', 'g')
        ->addSelect('g')
        ->where('a.patternSignature = :sig')
        ->setParameter('sig', $signature)
        ->orderBy($sortColumn, $sortDir)
        ->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit);

    $activities = $qb->getQuery()->getResult();

    // Count total
    $total = (int) $this->createQueryBuilder('a')
        ->select('COUNT(a.id)')
        ->where('a.patternSignature = :sig')
        ->setParameter('sig', $signature)
        ->getQuery()
        ->getSingleScalarResult();

    return ['activities' => $activities, 'total' => $total];
}
```

### Step 2: Controller Action
Update the existing `patternGroup` action:
```php
#[Route('/activities/pattern/{signature}', name: 'activity_pattern_detail')]
public function patternDetail(string $signature, Request $request, ActivityRepository $repo): Response
{
    $page = max(1, $request->query->getInt('page', 1));
    $sort = $request->query->getString('sort', 'date');
    $direction = $request->query->getString('direction', 'DESC');

    $result = $repo->findByPatternSignaturePaginated($signature, $page, 25, $sort, $direction);
    $totalPages = (int) ceil($result['total'] / 25);

    return $this->render('activity/pattern_detail.html.twig', [
        'signature' => $signature,
        'activities' => $result['activities'],
        'total' => $result['total'],
        'page' => $page,
        'totalPages' => $totalPages,
        'sort' => $sort,
        'direction' => $direction,
    ]);
}
```

Read the existing controller to understand the current `patternGroup` action, its route name, and any trend text logic. Preserve trend text computation.

### Step 3: Template
Create `templates/activity/pattern_detail.html.twig`:

Key elements:
- Wrap table + pagination in `<turbo-frame id="pattern-table">`
- Column headers as links:
```twig
{% macro sort_link(label, column, currentSort, currentDirection, signature, page) %}
    {% set newDirection = (currentSort == column and currentDirection == 'ASC') ? 'DESC' : 'ASC' %}
    <a href="{{ path('activity_pattern_detail', {signature: signature, sort: column, direction: newDirection, page: page}) }}"
       data-turbo-frame="pattern-table"
       class="hover:text-strava-orange">
        {{ label }}
        {% if currentSort == column %}
            {{ currentDirection == 'ASC' ? '▲' : '▼' }}
        {% endif %}
    </a>
{% endmacro %}
```

Table columns: Date, Name, Distance, Pace, Duration, Avg HR, Gear, Compare checkbox.

Pagination:
```twig
<div class="flex justify-center gap-2 mt-4">
    {% if page > 1 %}
        <a href="{{ path('activity_pattern_detail', {signature: signature, page: page - 1, sort: sort, direction: direction}) }}"
           data-turbo-frame="pattern-table" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300">← Prev</a>
    {% endif %}

    <span class="px-3 py-1">Page {{ page }} of {{ totalPages }}</span>

    {% if page < totalPages %}
        <a href="{{ path('activity_pattern_detail', {signature: signature, page: page + 1, sort: sort, direction: direction}) }}"
           data-turbo-frame="pattern-table" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300">Next →</a>
    {% endif %}
</div>
```

### Step 4: Comparison Stimulus Controller
Create `assets/controllers/comparison-selector_controller.js`:
```js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'compareButton'];
    static values = { selected: { type: Array, default: [] } };

    toggle(event) {
        const id = event.currentTarget.value;
        if (event.currentTarget.checked) {
            this.selectedValue = [...this.selectedValue, id];
        } else {
            this.selectedValue = this.selectedValue.filter(v => v !== id);
        }
        this.updateButton();
    }

    updateButton() {
        const btn = this.compareButtonTarget;
        btn.disabled = this.selectedValue.length < 2;
        btn.textContent = `Compare (${this.selectedValue.length})`;
    }

    compare() {
        const ids = this.selectedValue.join(',');
        window.location.href = `/activities/compare?ids=${ids}`;
    }
}
```

Read the existing comparison flow (controller, template) to understand how activity IDs are currently submitted. Adapt the Stimulus controller accordingly.

### Step 5: Preserve Trend Text
Read the existing `patternGroup` action to find any trend text computation. Keep it unchanged and pass it to the new template.

</details>
