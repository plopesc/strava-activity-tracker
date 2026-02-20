---
id: 4
group: "pattern-pages"
dependencies: [1]
status: "pending"
created: "2026-02-19"
skills: ["php", "twig"]
---
# Build Pattern List Page

## Objective
Move the existing grouped-activities view to `/activities/pattern` with an alphabetical layout showing each pattern group as a card with the 5 most recent activities and client-side sortable columns via a Stimulus controller.

## Skills Required
- `php`: Controller action with repository queries for pattern groups with limited recent activities
- `twig`: Card-based layout with sortable tables, Stimulus integration

## Acceptance Criteria
- [ ] `/activities/pattern` displays all distinct pattern groups ordered alphabetically by signature
- [ ] Each group shows: pattern signature as heading, total activity count, and a compact table of the 5 most recent activities (by date descending)
- [ ] Table columns: date, name, distance, pace, avg HR
- [ ] Column headers are clickable for client-side sorting (via Stimulus controller)
- [ ] Sorting uses `data-sort-value` attributes for numeric sorting on formatted values (pace, distance)
- [ ] Each group heading links to the full pattern detail page (`/activities/pattern/{signature}`)
- [ ] Unclassified activities appear at the bottom of the list
- [ ] PHPStan level 8 and PHP-CS-Fixer pass

Use your internal Todo tool to track these and keep on track.

## Technical Requirements
- Controller action on `ActivityController` at route `/activities/pattern`
- Repository method to fetch grouped pattern data with counts and 5 most recent activities per group
- Stimulus controller `assets/controllers/sortable-table_controller.js` for client-side column sorting
- Raw numeric values stored in `data-sort-value` attributes on table cells
- Tailwind-styled cards with alternating row backgrounds and hover states

## Input Dependencies
- Task 1: AssetMapper, Tailwind, Stimulus, and base template must be configured

## Output Artifacts
- Updated or new controller action at `/activities/pattern` (route name: `activity_pattern_list`)
- Repository method for grouped pattern data
- `templates/activity/pattern_list.html.twig` template
- `assets/controllers/sortable-table_controller.js` Stimulus controller

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### Step 1: Repository Method
The existing `findGroupedByPattern()` method may be close to what's needed. Read it first. If it returns all activities per group, create a new method or modify the controller to slice results:

```php
public function findPatternGroupsWithRecentActivities(int $limit = 5): array
{
    // Get all distinct pattern signatures with counts
    $groups = $this->createQueryBuilder('a')
        ->select('a.patternSignature, COUNT(a.id) as activityCount')
        ->groupBy('a.patternSignature')
        ->orderBy('a.patternSignature', 'ASC')
        ->getQuery()
        ->getResult();

    $result = [];
    foreach ($groups as $group) {
        $signature = $group['patternSignature'];
        $activities = $this->createQueryBuilder('a')
            ->leftJoin('a.gear', 'g')
            ->addSelect('g')
            ->where('a.patternSignature = :sig')
            ->setParameter('sig', $signature)
            ->orderBy('a.activityDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $result[] = [
            'signature' => $signature,
            'count' => $group['activityCount'],
            'activities' => $activities,
        ];
    }

    // Sort so null/unclassified signatures go to the bottom
    usort($result, function ($a, $b) {
        if ($a['signature'] === null) return 1;
        if ($b['signature'] === null) return -1;
        return strcasecmp($a['signature'], $b['signature']);
    });

    return $result;
}
```

### Step 2: Controller Action
```php
#[Route('/activities/pattern', name: 'activity_pattern_list')]
public function patternList(ActivityRepository $repo): Response
{
    return $this->render('activity/pattern_list.html.twig', [
        'groups' => $repo->findPatternGroupsWithRecentActivities(),
    ]);
}
```

Ensure this route is registered BEFORE `/activities/pattern/{signature}` to avoid routing conflicts (or use proper route priority).

### Step 3: Template
Create `templates/activity/pattern_list.html.twig`:
```twig
{% extends 'base.html.twig' %}

{% block body %}
<div class="max-w-6xl mx-auto p-4 space-y-6">
    <h1 class="text-2xl font-bold">Activity Patterns</h1>

    {% for group in groups %}
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-center mb-3">
            <a href="{{ path('activity_pattern_detail', {signature: group.signature ?? 'unclassified'}) }}"
               class="text-lg font-semibold text-strava-orange hover:underline">
                {{ group.signature ?? 'Unclassified' }}
            </a>
            <span class="text-sm text-gray-500">{{ group.count }} activities</span>
        </div>

        <div class="overflow-x-auto" data-controller="sortable-table">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b">
                        <th class="p-2 cursor-pointer" data-action="click->sortable-table#sort" data-col="0">Date</th>
                        <th class="p-2 cursor-pointer" data-action="click->sortable-table#sort" data-col="1">Name</th>
                        <th class="p-2 cursor-pointer" data-action="click->sortable-table#sort" data-col="2">Distance</th>
                        <th class="p-2 cursor-pointer" data-action="click->sortable-table#sort" data-col="3">Pace</th>
                        <th class="p-2 cursor-pointer" data-action="click->sortable-table#sort" data-col="4">Avg HR</th>
                    </tr>
                </thead>
                <tbody data-sortable-table-target="body">
                    {% for activity in group.activities %}
                    <tr class="border-b hover:bg-gray-50 {{ cycle(['bg-white', 'bg-gray-25'], loop.index0) }}">
                        <td class="p-2" data-sort-value="{{ activity.activityDate|date('U') }}">{{ activity.activityDate|date('M j, Y') }}</td>
                        <td class="p-2">{{ activity.name }}</td>
                        <td class="p-2" data-sort-value="{{ activity.distance }}">{{ (activity.distance / 1000)|number_format(2) }} km</td>
                        <td class="p-2" data-sort-value="{{ activity.averageSpeed }}">{{ activity.averageSpeed|pace_format }}</td>
                        <td class="p-2" data-sort-value="{{ activity.averageHeartrate ?? 0 }}">{{ activity.averageHeartrate ?? '—' }}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
    </div>
    {% endfor %}
</div>
{% endblock %}
```

### Step 4: Stimulus Sortable Table Controller
Create `assets/controllers/sortable-table_controller.js`:
```js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['body'];

    sort(event) {
        const col = parseInt(event.currentTarget.dataset.col);
        const tbody = this.bodyTarget;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const isAsc = event.currentTarget.dataset.direction !== 'asc';
        event.currentTarget.dataset.direction = isAsc ? 'asc' : 'desc';

        rows.sort((a, b) => {
            const aCell = a.children[col];
            const bCell = b.children[col];
            const aVal = parseFloat(aCell.dataset.sortValue) || aCell.textContent.trim();
            const bVal = parseFloat(bCell.dataset.sortValue) || bCell.textContent.trim();

            if (typeof aVal === 'number' && typeof bVal === 'number') {
                return isAsc ? aVal - bVal : bVal - aVal;
            }
            return isAsc
                ? String(aVal).localeCompare(String(bVal))
                : String(bVal).localeCompare(String(aVal));
        });

        rows.forEach(row => tbody.appendChild(row));
    }
}
```

Note: Verify that the Twig filter names (`pace_format`, `duration_format`) match the actual registered names in `src/Twig/`. Read those files to confirm.

</details>
