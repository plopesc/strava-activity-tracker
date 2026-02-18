---
id: 8
group: "testing"
dependencies: [4]
status: "pending"
created: 2026-02-18
skills:
  - php
  - phpunit
---
# Pattern Recognition Tests

## Objective
Write PHPUnit integration tests that verify the core business logic of the `PatternRecognizer` service: correct classification of each workout type, accurate segment extraction (both lap-based and stream-based), pattern matching with tolerance, and signature generation. Focus on the algorithm's custom logic — not on Doctrine or Symfony framework internals.

## Skills Required
- PHPUnit
- PHP testing patterns

## Acceptance Criteria
- [ ] Test suite can be run with `php bin/phpunit` and passes with no failures
- [ ] Short run classification: an activity with distance 9 000 m and low pace variance is classified as `short_run`
- [ ] Long run classification: an activity with distance 15 000 m and low pace variance is classified as `long_run`
- [ ] Interval classification from laps: an activity with 8 laps of alternating fast/slow distances is classified as `interval` and produces a correct segment array
- [ ] Interval classification from stream fallback: an activity with 1 uniform lap but a pace stream showing alternating fast/slow zones is classified as `interval` with correct segments
- [ ] Signature string matches the expected format (`"2km warmup + 3×1km fast + 3×500m recovery + 1km cooldown"`)
- [ ] `haveSamePattern()` returns `true` for two interval activities whose segments differ by < 10 %
- [ ] `haveSamePattern()` returns `false` for two interval activities whose segment distances differ by > 10 %
- [ ] `haveSamePattern()` returns `true` for any two `short_run` activities regardless of distance
- [ ] Activities with no lap and no stream data receive `patternType = null`

**Meaningful Test Strategy Guidelines** (copy for reference):
- Write a few tests, mostly integration — test the whole `classify()` method end-to-end with realistic fixture data, not individual private helpers
- Do not test Doctrine persistence or Symfony container wiring here — those are framework concerns
- Use in-memory `Activity` objects (no DB needed) as fixtures

## Technical Requirements
- PHPUnit (via `symfony/test-pack` or `phpunit/phpunit`)
- No database, no HTTP client — all fixtures are plain PHP arrays assigned to `Activity` entity fields

## Input Dependencies
- Task 04: `PatternRecognizer` service and `Activity` entity with all pattern fields

## Output Artifacts
- `tests/Pattern/PatternRecognizerTest.php`

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

1. **Test setup**: In `PatternRecognizerTest::setUp()`, instantiate `PatternRecognizer` directly (no container needed) with default threshold values.

2. **Activity fixture helper**: Create a private `makeActivity(float $distanceM, array $rawLaps, array $rawStreams): Activity` method that `new Activity()` and sets the fields via setters (or via reflection if no public setters exist). This avoids repetition across tests.

3. **Low variance stream fixture**: Construct a `velocity_smooth` stream where all values are near-constant (e.g., 3.5 ± 0.1 m/s) with enough data points to match the activity distance.

4. **Interval lap fixture**: Build 8 laps alternating fast (avg_speed ≈ 4.2 m/s, distance 1 000 m) and slow (avg_speed ≈ 2.8 m/s, distance 500 m). Set `rawStreams = []` to force lap-based path.

5. **Stream fallback fixture**: Set `rawLaps = [one single lap]` covering the whole distance. Build a `velocity_smooth` stream alternating 60 seconds at 4.2 m/s and 30 seconds at 2.5 m/s, repeated 4 times plus a warmup/cooldown at 3.0 m/s.

6. **Pattern matching test**: Use two activity fixtures with segment distances that are 5 % apart (should match) and two with distances 15 % apart (should not match).

7. **Null data test**: Set `rawLaps = null`, `rawStreams = null` and verify `patternType` remains null after `classify()`.

8. Keep the total test count focused: aim for 8–10 test methods covering the cases in the acceptance criteria. Avoid testing internal helper methods directly.

</details>
