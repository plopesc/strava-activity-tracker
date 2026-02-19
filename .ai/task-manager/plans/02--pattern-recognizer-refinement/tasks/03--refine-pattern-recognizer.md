---
id: 3
group: "pattern-recognizer"
dependencies: [1]
status: "pending"
created: 2026-02-19
skills:
  - php
---
# Refine PatternRecognizer: signatures, segment merging, and segment stats

## Objective
Update `PatternRecognizer` so that short/long runs produce "easy" segments with HR/speed stats, interval workouts use compact signatures merging non-consecutive same-type training segments (fast + moderate), and all segments carry `avg_speed`, `avg_heartrate`, and `max_heartrate`.

## Skills Required
PHP 8.4

## Acceptance Criteria
- [ ] Short/long run segments: single `{"type":"easy","distance_m":<floored>,"count":1,"avg_speed":…,"avg_heartrate":…,"max_heartrate":…}`
- [ ] Short/long run signature: `"easy Nkm"` using floor (e.g. 9500m → "easy 9km")
- [ ] `extractTrainingSegments()` returns `fast` and `moderate` only (not `recovery`)
- [ ] New `mergeTrainingSegmentsByTypeAndDistance()` groups non-consecutive same-type same-rounded-distance training segments
- [ ] Interval lap-based segments carry `avg_speed`, `avg_heartrate`, `max_heartrate` from rawLaps
- [ ] When `mergeSameType()` merges consecutive same-type segments, it computes distance-weighted avg_speed/avg_heartrate and max of max_heartrate
- [ ] Stream-based segments carry `avg_speed` (computed from stream), `avg_heartrate: null`, `max_heartrate: null`
- [ ] Interval signatures format: `"4×1km + 8km"`, `"2×6km"` (fast + moderate only, recovery excluded)
- [ ] Existing passing tests still pass; new test cases cover easy-run segments and "2×6km" signature

## Technical Requirements
File: `src/Pattern/PatternRecognizer.php`

### Change 1 — Easy run segments (short_run / long_run branches in classify())
```php
$easyDistance = floor($distance / 1000) * 1000;
$easyKm = (int)($easyDistance / 1000);
$activity->setPatternSegments([[
    'type' => 'easy',
    'distance_m' => $easyDistance,
    'count' => 1,
    'avg_speed' => $activity->getAverageSpeed(),
    'avg_heartrate' => $activity->getAverageHeartrate(),
    'max_heartrate' => $activity->getMaxHeartrate(),
]]);
$activity->setPatternSignature('easy ' . $easyKm . 'km');
```

### Change 2 — extractTrainingSegments()
Change filter to `['fast', 'moderate']` (remove `'recovery'`).

### Change 3 — Segment stats in segmentByLaps()
When building `$labeled` entries per lap, include:
```php
$labeled[] = [
    'type' => $type,
    'distance_m' => $distance,
    'count' => 1,
    'avg_speed' => isset($lap['average_speed']) ? (float)$lap['average_speed'] : null,
    'avg_heartrate' => isset($lap['average_heartrate']) ? (float)$lap['average_heartrate'] : null,
    'max_heartrate' => isset($lap['max_heartrate']) ? (float)$lap['max_heartrate'] : null,
];
```

### Change 4 — mergeSameType() to aggregate stats
When merging two segments of the same type, compute:
- `avg_speed`: distance-weighted average `(a.avg_speed * a.distance_m + b.avg_speed * b.distance_m) / (a.distance_m + b.distance_m)` — treat null as 0
- `avg_heartrate`: same distance-weighted average; if both null, keep null
- `max_heartrate`: `max($a['max_heartrate'] ?? 0, $b['max_heartrate'] ?? 0)` — keep null if both null

### Change 5 — Stream segments avg_speed
In `segmentByStream()`, when building segment entries, include:
```php
'avg_speed' => $avgSpeed,
'avg_heartrate' => null,
'max_heartrate' => null,
```

### Change 6 — New mergeTrainingSegmentsByTypeAndDistance()
Add a private method:
```
private function mergeTrainingSegmentsByTypeAndDistance(array $trainingSegments): array
```
Logic:
1. For each segment, compute a group key: `$type . '_' . (round($dist / 100) * 100)`
2. Maintain ordered list of first-seen keys to preserve insertion order
3. Accumulate: sum `count`, sum `distance_m`, accumulate for weighted avg_speed/avg_heartrate, track max of max_heartrate
4. Return entries in first-seen order

### Change 7 — buildSignature() calls new method
```php
$training = $this->extractTrainingSegments($segments);
$merged = $this->mergeTrainingSegmentsByTypeAndDistance($training);
// format each $merged entry as "N×Xkm" or "Xkm"
```

## Input Dependencies
- `src/Entity/Activity.php` with `getMaxHeartrate()` (Task 01)

## Output Artifacts
- Updated `src/Pattern/PatternRecognizer.php`
- Updated `tests/Pattern/PatternRecognizerTest.php`

## Implementation Notes

<details>
<summary>Key details</summary>

- `applyWarmupCooldown()` runs before signature building and relabels first/last `moderate` as `warmup`/`cooldown`. So only internal `moderate` segments reach `extractTrainingSegments()`. This is already correct behaviour — no change needed there.
- For the easy segment, `$activity->getMaxHeartrate()` may be null if the activity has no HR data; the segment should store null in that case.
- For the `mergeTrainingSegmentsByTypeAndDistance` weighted average: if `avg_speed` is null for a segment (shouldn't happen for lap-based), treat it as 0 and weight accordingly. If both `avg_heartrate` values are null, the merged entry's `avg_heartrate` should be null (not 0).
- Update tests:
  - Adjust any test asserting old `"short_run"` signature to use `"easy Nkm"` format
  - Add a test with 2 separate moderate laps of ~6km and assert signature is `"2×6km"`
  - Add a test verifying easy segment has `avg_speed`, `avg_heartrate`, `max_heartrate` keys

</details>
