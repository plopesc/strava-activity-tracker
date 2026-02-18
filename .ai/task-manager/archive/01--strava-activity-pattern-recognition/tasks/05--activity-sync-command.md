---
id: 5
group: "sync"
dependencies: [2, 3, 4]
status: "completed"
created: 2026-02-18
skills:
  - php
  - symfony
---
# Activity Sync Console Command

## Objective
Implement the `strava:sync` Symfony Console command that fetches running activities from the Strava API, stores them in SQLite with their lap and stream data, and runs the pattern recognizer on each new or updated activity. Incremental sync is the default; a `--full` flag triggers a full re-fetch.

## Skills Required
- Symfony Console command
- PHP service orchestration

## Acceptance Criteria
- [ ] Command is registered as `strava:sync` and runs via `php bin/console strava:sync`
- [ ] On first run (no activities in DB), all pages of running activities are fetched until the API returns an empty page
- [ ] On subsequent runs, only activities with `start_date` after the most recent `activityDate` in the database are fetched (uses Strava `after` parameter)
- [ ] `--full` option forces re-fetch of all activities regardless of existing data
- [ ] For each activity fetched, the command calls `getActivity()` for detail + laps and `getActivityStreams()` for pace and HR streams; both payloads are stored on the entity
- [ ] If an activity with the same `stravaId` already exists in DB, its fields are updated rather than a duplicate created
- [ ] `PatternRecognizer::classify()` is called for every inserted or updated activity before flushing
- [ ] Rate limit awareness: the command calls `$client->sleepIfNeeded()` before each API call and outputs a message when sleeping
- [ ] Progress is reported to the console output: one line per activity (`[date] [name] → [pattern_signature]`)
- [ ] Command exits with code 0 on success, 1 on API error

## Technical Requirements
- Symfony Console (`Command`, `InputInterface`, `OutputInterface`)
- `StravaClient` (Task 03), `PatternRecognizer` (Task 04), `ActivityRepository` (Task 02), Doctrine `EntityManagerInterface`

## Input Dependencies
- Task 02: `Activity` entity + `ActivityRepository::findLatestSyncedAt()` and upsert logic
- Task 03: `StravaClient` with `getActivities()`, `getActivity()`, `getActivityStreams()`
- Task 04: `PatternRecognizer::classify()`

## Output Artifacts
- `src/Command/StravasSyncCommand.php`

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

1. **Command skeleton**:
   ```
   #[AsCommand(name: 'strava:sync', description: 'Sync Strava running activities')]
   class StravaSyncCommand extends Command
   ```
   Use autowiring to inject `StravaClient`, `PatternRecognizer`, `ActivityRepository`, and `EntityManagerInterface`.

2. **Incremental sync logic**:
   - Call `$repo->findLatestSyncedAt()` to get a `\DateTimeImmutable` or `null`.
   - If `null` (or `--full`), set `$after = null`; otherwise `$after = $latestDate->getTimestamp()`.
   - Fetch pages: loop `$page = 1`, call `getActivities($page, 50, $after)`, break when result is empty.

3. **Per-activity processing**:
   - For each activity in the page response:
     a. Call `getActivity($stravaId)` — returns full detail including `laps` array.
     b. Call `getActivityStreams($stravaId)` — returns stream data (may be empty array if no streams).
     c. Upsert: `$activity = $repo->findOneBy(['stravaId' => $id]) ?? new Activity()`.
     d. Map all fields from the API response onto the entity.
     e. Set `rawLaps` from the detail response's `laps` key.
     f. Set `rawStreams` from the streams response.
     g. Set `syncedAt = new \DateTimeImmutable()`.
     h. Call `$recognizer->classify($activity)`.
     i. `$em->persist($activity)`.
   - Flush in batches of 20 activities to avoid large transactions: `if ($count % 20 === 0) { $em->flush(); $em->clear(); }`. After the loop, call `$em->flush()` once more.

4. **Strava field mapping** (key API response fields):
   - `id` → `stravaId`
   - `name` → `name`
   - `start_date` → `activityDate` (parse ISO 8601 string to `\DateTimeImmutable`)
   - `distance` → `distance` (metres, float)
   - `elapsed_time` → `elapsedTime` (seconds, int)
   - `average_speed` → `averageSpeed` (m/s)
   - `average_heartrate` → `averageHeartrate` (nullable float)

5. **Filtering**: Strava's list endpoint accepts `type=Run` implicitly via `getActivities()` since `StravaClient` hard-filters to `type=Run` in the query string. No additional filtering needed in the command.

6. **Output format**: Use `$output->writeln()`:
   ```
   [2025-06-01] Morning Run → 3×1km fast + 2×500m recovery
   Sleeping 45s for rate limit...
   Sync complete: 42 activities processed.
   ```

7. **Error handling**: Wrap the entire sync loop in a try/catch on `\RuntimeException` (thrown by `StravaClient` on API errors). On catch: `$output->writeln("<error>{$e->getMessage()}</error>")` and return `Command::FAILURE`.

</details>
