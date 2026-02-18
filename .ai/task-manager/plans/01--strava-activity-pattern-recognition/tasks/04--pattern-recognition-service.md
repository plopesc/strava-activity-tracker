---
id: 4
group: "pattern-recognition"
dependencies: [2]
status: "completed"
created: 2026-02-18
skills:
  - php
complexity_score: 5
complexity_notes: "Core algorithmic task: pace-variance classification, lap-based segmentation, stream fallback segmentation, and pattern matching with tolerance. Kept as one task because all steps operate on the same entity and share internal helper methods."
---
# Pattern Recognition Service

## Objective
Implement the `PatternRecognizer` service that reads an `Activity` entity's stored raw laps and pace/HR stream data, classifies it into one of three pattern types (`short_run`, `long_run`, `interval`), produces a structured segment array, and generates a human-readable pattern signature. Also expose a method to determine whether two activities share the same pattern structure within configurable distance tolerances.

## Skills Required
- PHP algorithmic / data processing

## Acceptance Criteria
- [ ] `PatternRecognizer::classify(Activity $activity): void` sets `patternType`, `patternSignature`, and `patternSegments` on the entity (does not persist — caller flushes)
- [ ] Activities with distance 8 000–12 000 m and pace coefficient of variation ≤ 10 % are classified as `short_run`
- [ ] Activities with distance > 12 000 m and pace coefficient of variation ≤ 10 % are classified as `long_run`
- [ ] Activities that do not meet the steady-pace thresholds are processed through the interval segmentation path
- [ ] Lap-based segmentation is used when the activity has ≥ 3 laps with a mean absolute deviation of lap distances > 200 m
- [ ] Stream-based segmentation is used as fallback when lap data is not suitable
- [ ] Segment sequence is an ordered array of objects: `[{type: "fast|recovery|moderate|warmup|cooldown", distance_m: float, count: int}]`
- [ ] Consecutive laps/stream-zones of the same type are merged into a single segment with `count` incremented
- [ ] Human-readable signature for `short_run` and `long_run` is `"short_run"` / `"long_run"` (distance implied by type)
- [ ] Human-readable signature for intervals is built from the **training segments only** (fast and recovery), ignoring warmup and cooldown distances: e.g., `"3×1km fast + 3×500m recovery"` (distances rounded to nearest 100 m, expressed in km when ≥ 1 000 m); warmup and cooldown are present in `patternSegments` but excluded from the signature
- [ ] `PatternRecognizer::haveSamePattern(Activity $a, Activity $b): bool` returns `true` when both have the same `patternType` and, for intervals, only the **training segments** (fast and recovery, in order) match within ± 10 % distance tolerance — warmup and cooldown segments are excluded from the comparison
- [ ] Activities with insufficient data (no raw laps or streams) that cannot be resolved receive `patternType = null` and a null signature

## Technical Requirements
- Pure PHP — no external libraries
- Configurable thresholds via constructor parameters (with defaults): pace CV threshold (10 %), lap MAD threshold (200 m), segment tolerance (10 %)
- Works on already-hydrated `Activity` entity; does not call Strava API or touch the database

## Input Dependencies
- Task 02: `Activity` entity class with `rawLaps`, `rawStreams`, `patternType`, `patternSignature`, `patternSegments` fields

## Output Artifacts
- `src/Pattern/PatternRecognizer.php` — injectable Symfony service

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

### Pace Coefficient of Variation (CV)
Use the `velocity_smooth` array from `rawStreams['velocity_smooth']['data']` (m/s values).
- Convert to pace (min/km) = 1000 / (velocity * 60) for readability, but CV calculation works equally on speed.
- CV = stddev(values) / mean(values).
- If the stream is absent, treat CV as "high" (> threshold) so the activity falls into the interval path.

### Lap-based Segmentation
1. Extract laps from `rawLaps` array. Each Strava lap has `average_speed` (m/s) and `distance` (m).
2. Compute median lap speed across all laps.
3. Classify each lap:
   - `fast` if lap speed > 1.15 × median
   - `recovery` if lap speed < 0.85 × median
   - `moderate` otherwise
4. Apply warmup/cooldown heuristics: if the first segment is `moderate` and all middle segments are `fast`/`recovery`, relabel the first segment as `warmup` and the last as `cooldown`.
5. Merge consecutive same-type laps into one segment, summing distance and incrementing count.
6. Check suitability: use laps when `count(rawLaps) >= 3` AND `mean_absolute_deviation(lap_distances) > 200`.

### Stream-based Segmentation (fallback)
1. Use `rawStreams['velocity_smooth']['data']` (array of per-second speed values).
2. Apply a simple moving average with a 30-sample window to smooth noise.
3. Compute global median speed.
4. Classify each smoothed sample as `fast` (> 1.15 × median), `recovery` (< 0.85 × median), or `moderate`.
5. Group consecutive same-classification samples into segments.
6. Filter out segments shorter than 200 m (noise suppression).
7. Merge adjacent same-type segments.
8. Convert sample counts to distances: each sample represents 1 second; distance_m ≈ speed × 1.

### Pattern Matching (`haveSamePattern`)
1. If either activity has `patternType = null`, return `false`.
2. If types differ, return `false`.
3. For `short_run` / `long_run`, return `true` (type match is sufficient).
4. For `interval`: decode `patternSegments` JSON for each activity. Extract only the training segments (type `fast` or `recovery`) in order, discarding any `warmup`, `cooldown`, and `moderate` segments. If the training segment counts differ, return `false`. For each pair of corresponding training segments: check same type AND `|distA - distB| / max(distA, distB) <= 0.10`.

### Signature generation
- Build signature string from **training segments only** (type `fast` or `recovery`); warmup, cooldown, and moderate segments are stored in `patternSegments` but excluded from the signature string.
  - Format each segment: if count > 1 → `"{count}×{dist}"`, else `"{dist}"` where dist is expressed as `{N}km` if ≥ 1000 m else `{N}m` (rounded to nearest 100).
  - Join all segments with ` + `.
- Example: segments `[{warmup,2000,1},{fast,1000,3},{recovery,500,3},{cooldown,1000,1}]` → signature is `"3×1km fast + 3×500m recovery"` (warmup and cooldown omitted).

</details>
