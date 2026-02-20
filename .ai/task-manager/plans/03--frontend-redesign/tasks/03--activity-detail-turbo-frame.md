---
id: 3
group: "calendar"
dependencies: [2]
status: "pending"
created: "2026-02-19"
skills: ["php", "twig"]
---
# Build Activity Detail Turbo Frame

## Objective
Create a controller action and partial Twig template that displays activity details inside the `activity-detail` Turbo Frame when a calendar icon is clicked, without a full page reload. Add a Stimulus controller to manage the visual "selected" state on calendar icons.

## Skills Required
- `php`: New controller action returning a partial template
- `twig`: Turbo Frame partial template, Stimulus controller for selected state

## Acceptance Criteria
- [ ] A new route `/activities/{id}/detail` returns a partial template wrapped in `<turbo-frame id="activity-detail">`
- [ ] The partial displays: activity name, date, distance, pace, duration, average HR, max HR, gear name, pattern type, and pattern signature
- [ ] Includes a link to view the full pattern group (`/activities/pattern/{signature}`)
- [ ] Clicking a calendar icon loads the detail into the sidebar without full page reload
- [ ] A Stimulus controller highlights the currently selected calendar icon (e.g., ring or scale effect)
- [ ] On mobile, the detail panel appears inline below the calendar/list
- [ ] If the activity has no gear, display a dash instead of null
- [ ] PHPStan level 8 and PHP-CS-Fixer pass

Use your internal Todo tool to track these and keep on track.

## Technical Requirements
- Controller action on `ActivityController` returning a Turbo Frame partial
- Partial template `templates/activity/_detail.html.twig`
- Stimulus controller `assets/controllers/calendar-selection_controller.js` for visual selected state
- Pace formatting using existing `pace_format` Twig extension
- Duration formatting using existing `duration_format` Twig extension

## Input Dependencies
- Task 2: Calendar page template must exist with the `activity-detail` Turbo Frame placeholder and calendar icon links targeting this frame

## Output Artifacts
- New controller action at `/activities/{id}/detail`
- `templates/activity/_detail.html.twig` partial template
- `assets/controllers/calendar-selection_controller.js` Stimulus controller

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### Step 1: Controller Action
Add to `ActivityController`:
```php
#[Route('/activities/{id}/detail', name: 'activity_detail')]
public function detail(Activity $activity): Response
{
    return $this->render('activity/_detail.html.twig', [
        'activity' => $activity,
    ]);
}
```

Symfony's param converter will automatically fetch the Activity entity by ID.

### Step 2: Partial Template
Create `templates/activity/_detail.html.twig`:
```twig
<turbo-frame id="activity-detail">
    <div class="bg-white rounded-lg shadow p-4 space-y-3">
        <h3 class="font-semibold text-lg">{{ activity.name }}</h3>
        <p class="text-sm text-gray-500">{{ activity.activityDate|date('D, M j, Y') }}</p>

        <dl class="grid grid-cols-2 gap-2 text-sm">
            <dt class="text-gray-500">Distance</dt>
            <dd>{{ (activity.distance / 1000)|number_format(2) }} km</dd>

            <dt class="text-gray-500">Pace</dt>
            <dd>{{ activity.averageSpeed|pace_format }}</dd>

            <dt class="text-gray-500">Duration</dt>
            <dd>{{ activity.elapsedTime|duration_format }}</dd>

            <dt class="text-gray-500">Avg HR</dt>
            <dd>{{ activity.averageHeartrate ?? '—' }}</dd>

            <dt class="text-gray-500">Max HR</dt>
            <dd>{{ activity.maxHeartrate ?? '—' }}</dd>

            <dt class="text-gray-500">Gear</dt>
            <dd>{{ activity.gear ? activity.gear.name : '—' }}</dd>

            <dt class="text-gray-500">Pattern</dt>
            <dd>{{ activity.patternType ?? 'unclassified' }}</dd>

            <dt class="text-gray-500">Signature</dt>
            <dd>{{ activity.patternSignature ?? '—' }}</dd>
        </dl>

        {% if activity.patternSignature %}
            <a href="{{ path('activity_pattern_detail', {signature: activity.patternSignature}) }}"
               class="block text-center text-sm text-strava-orange hover:underline mt-2">
                View pattern group →
            </a>
        {% endif %}
    </div>
</turbo-frame>
```

Note: Check exact Twig extension filter names by reading `src/Twig/` files. The names above are examples — use the actual registered filter names.

### Step 3: Stimulus Controller
Create `assets/controllers/calendar-selection_controller.js`:
```js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['icon'];

    select(event) {
        // Remove selected class from all icons
        this.iconTargets.forEach(icon => {
            icon.classList.remove('ring-2', 'ring-strava-orange', 'scale-125');
        });
        // Add selected class to clicked icon
        event.currentTarget.classList.add('ring-2', 'ring-strava-orange', 'scale-125');
    }
}
```

In the calendar template (Task 2), wrap the calendar area with the Stimulus controller and add data attributes to each activity icon:
```twig
<div data-controller="calendar-selection">
    {# ... calendar grid ... #}
    <a href="..."
       data-turbo-frame="activity-detail"
       data-calendar-selection-target="icon"
       data-action="click->calendar-selection#select"
       class="...">
    </a>
</div>
```

### Step 4: Mobile Layout
The detail panel is already positioned above or below the calendar in the flex layout from Task 2. On mobile (`md:` breakpoint), the sidebar appears as a full-width section. No additional work needed if Task 2's layout is correct — just verify the detail loads inline on small viewports.

</details>
