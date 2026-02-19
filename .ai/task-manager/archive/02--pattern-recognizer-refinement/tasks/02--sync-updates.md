---
id: 2
group: "sync"
dependencies: [1]
status: "completed"
created: 2026-02-19
skills:
  - php
  - symfony
---
# Update StravaSyncCommand: sport-type filter, gear upsert, maxHeartrate

## Objective
Update `StravaSyncCommand` and `StravaClient` so that only activities matching `AllowedSportType` are synced, gear data is upserted from the activity response, and `sportType` + `maxHeartrate` are stored on each `Activity`.

## Skills Required
PHP 8.4, Symfony console commands

## Acceptance Criteria
- [ ] `StravaClient::getActivities()` no longer sends the `type=Run` filter to the Strava API
- [ ] `StravaSyncCommand` skips any activity whose `sport_type` is not in `AllowedSportType::values()`
- [ ] Each synced `Activity` has `sportType` set from the Strava `sport_type` field
- [ ] Each synced `Activity` has `maxHeartrate` set from the Strava `max_heartrate` field (nullable)
- [ ] When a Strava activity has a `gear` object (with `id` and `name`), a `Gear` record is looked up by `stravaGearId` or created, and `Activity::$gear` is set
- [ ] When a Strava activity has no `gear` key or `gear_id` is null, `Activity::$gear` is set to null
- [ ] Existing tests still pass

## Technical Requirements

### `StravaClient::getActivities()`
Remove `'type' => 'Run'` from the `$query` array. The API will now return all activity types; filtering happens in the sync command.

### `StravaSyncCommand`

**Filtering**: After fetching each page of activities, filter:
```php
use App\Strava\AllowedSportType;
// ...
$activities = array_filter($page, fn($a) => in_array($a['sport_type'] ?? '', AllowedSportType::values(), true));
```

**New fields on Activity upsert**: When setting activity fields, also set:
```php
$activity->setSportType($data['sport_type'] ?? null);
$activity->setMaxHeartrate($data['max_heartrate'] ?? null);
```

**Gear upsert**: After setting the activity fields, before calling `PatternRecognizer::classify()`:
```php
$gearData = $data['gear'] ?? null;
if ($gearData && !empty($gearData['id'])) {
    $gear = $gearRepo->findOneBy(['stravaGearId' => $gearData['id']]);
    if (!$gear) {
        $gear = new Gear();
        $gear->setStravaGearId($gearData['id']);
        $gear->setName($gearData['name'] ?? 'Unknown');
        $em->persist($gear);
    }
    $activity->setGear($gear);
} else {
    $activity->setGear(null);
}
```

Note: `$data` here comes from `getActivity($stravaId)` (the detailed single-activity response), not from the list. Verify which fields are present in the detailed vs. list responses — `gear` and `max_heartrate` are typically in the detailed response.

## Input Dependencies
- `src/Entity/Gear.php` (Task 01)
- `src/Entity/Activity.php` with new fields (Task 01)
- `src/Strava/AllowedSportType.php` (Task 01)

## Output Artifacts
- Updated `src/Strava/StravaClient.php`
- Updated `src/Command/StravaSyncCommand.php`

## Implementation Notes

<details>
<summary>Key details</summary>

- Read `src/Command/StravaSyncCommand.php` fully before editing — understand the existing upsert logic, where `$data` comes from (the detailed activity response from `getActivity()`), and the batch flush pattern.
- The `gear` array in Strava's detailed activity response has keys `id` (string like `g123456`), `name`, `resource_state`. Use `$gearData['id']` as `stravaGearId`.
- `max_heartrate` in Strava's activity response may be `null` or missing for activities without a heart rate monitor.
- The gear `$em->persist($gear)` will be flushed as part of the existing batch flush (every 20 activities). No additional flush needed.
- Inject the gear repository via `$em->getRepository(Gear::class)` inside the command's `execute()` method — no need to add a constructor argument.

</details>
