---
id: 4
group: "classify-command"
dependencies: [3]
status: "pending"
created: 2026-02-19
skills:
  - php
  - symfony
---
# Implement strava:classify command (persist by default, --dry-run to skip)

## Objective
Create a Symfony console command `strava:classify {stravaId} [--dry-run]` that classifies a single activity by its Strava ID, persists the result to the database by default, and prints the classification output.

## Skills Required
PHP 8.4, Symfony console commands

## Acceptance Criteria
- [ ] Command `strava:classify {stravaId}` runs without error
- [ ] If the Strava ID exists in the DB, it re-classifies that Activity and updates it
- [ ] If the Strava ID is not in the DB, it fetches from the Strava API, creates a new `Activity`, classifies it, and persists it
- [ ] Classification result is always printed: `patternType`, `patternSignature`, `patternSegments` (JSON pretty-print)
- [ ] Without `--dry-run`: `$em->flush()` is called, changes are persisted
- [ ] With `--dry-run`: no flush; output notes "Dry run — changes not persisted"
- [ ] An informative error message is shown if the Strava API request fails

## Technical Requirements
New file: `src/Command/StravaClassifyCommand.php`

```
#[AsCommand(name: 'strava:classify')]
class StravaClassifyCommand extends Command
```

Constructor injects: `ActivityRepository`, `StravaClient`, `PatternRecognizer`, `EntityManagerInterface`

**Arguments/Options:**
- Argument `stravaId` (required, integer)
- Option `--dry-run` (boolean flag, no value)

**execute() logic:**
1. `$stravaId = (int) $input->getArgument('stravaId')`
2. `$activity = $activityRepository->findOneBy(['stravaId' => (string)$stravaId])`
3. If `$activity === null`:
   - `$data = $stravaClient->getActivity($stravaId)` — wrap in try/catch `\RuntimeException`, output error and return `Command::FAILURE`
   - `$streams = $stravaClient->getActivityStreams($stravaId)` — wrap in try/catch, allow null streams
   - Create `new Activity()`, set `stravaId`, `distance`, `rawLaps` (from `$data['laps'] ?? []`), `rawStreams` (from streams response), `averageSpeed`, `averageHeartrate`, `maxHeartrate`, `name`, `activityDate`, `elapsedTime`, `sportType`, `syncedAt = new \DateTimeImmutable()`
   - `$em->persist($activity)`
4. `$patternRecognizer->classify($activity)`
5. Print:
   ```
   Type:      <patternType>
   Signature: <patternSignature>
   Segments:  <json_encode(patternSegments, JSON_PRETTY_PRINT)>
   ```
6. If not `--dry-run`: `$em->flush()`, print "Persisted."
7. If `--dry-run`: print "Dry run — changes not persisted."
8. Return `Command::SUCCESS`

## Input Dependencies
- `src/Pattern/PatternRecognizer.php` (Task 03 — updated with new segment format)
- `src/Entity/Activity.php` with `getMaxHeartrate/setMaxHeartrate`, `getSportType/setSportType` (Task 01)

## Output Artifacts
- `src/Command/StravaClassifyCommand.php`

## Implementation Notes

<details>
<summary>Key details</summary>

- Read `src/Strava/StravaClient.php` before implementing — verify exact method signatures for `getActivity(int $stravaId)` and `getActivityStreams(int $stravaId)`.
- `getActivity()` returns the full activity JSON. The `laps` key contains lap array; `max_heartrate` and `average_heartrate` are top-level keys; `distance` is in metres; `start_date` is an ISO 8601 string for `activityDate`; `elapsed_time` is seconds.
- `getActivityStreams()` returns an associative array keyed by stream type (e.g. `['velocity_smooth' => ['data' => [...]], 'heartrate' => ['data' => [...]]]`). Store this directly as `rawStreams`.
- `activityDate` is `\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['start_date'])`.
- Do NOT set `gear` on the transient activity — gear upsert is handled by the sync command, not the classify command. Leave `gear = null`.
- For the existing activity case (found in DB): simply call `classify()` — do not re-fetch from the API. The existing `rawLaps` and `rawStreams` in the DB are sufficient.
- Use `$output->writeln()` throughout. Output the segments JSON after an empty line for readability.

</details>
