---
id: 2
group: "data-layer"
dependencies: [1]
status: "pending"
created: 2026-02-18
skills:
  - php
  - database
---
# Doctrine Entities and SQLite Migrations

## Objective
Define the `Activity` Doctrine entity with all fields required to store raw Strava data and computed pattern results, then generate and run the initial database migration so the SQLite schema is ready for use by the sync and pattern recognition tasks.

## Skills Required
- Doctrine ORM entity mapping
- SQLite schema via Doctrine Migrations

## Acceptance Criteria
- [ ] `Activity` entity class exists with all required mapped fields (see Technical Requirements)
- [ ] All fields that may be absent (heart rate, pattern fields, raw payloads) are declared nullable
- [ ] JSON-typed columns are used for `raw_laps`, `raw_streams`, and `pattern_segments`
- [ ] `strava_id` field has a unique constraint
- [ ] A Doctrine migration file is generated and `bin/console doctrine:migrations:migrate` creates the SQLite schema without errors
- [ ] `bin/console doctrine:schema:validate` reports the mapping as valid

## Technical Requirements
- Doctrine ORM with SQLite DBAL driver
- Doctrine Migrations bundle

### Activity Entity Fields

| Field | Type | Nullable | Notes |
|---|---|---|---|
| `id` | int | no | Auto-increment PK |
| `stravaId` | bigint | no | Unique; Strava's numeric activity ID |
| `name` | string(255) | no | Activity title from Strava |
| `activityDate` | datetime_immutable | no | Start date/time of the activity |
| `distance` | float | no | Total distance in metres |
| `elapsedTime` | int | no | Total elapsed time in seconds |
| `averageSpeed` | float | no | Average speed in m/s |
| `averageHeartrate` | float | yes | Null if no HR monitor |
| `rawLaps` | json | yes | Raw Strava laps array |
| `rawStreams` | json | yes | Raw pace + HR stream data keyed by type |
| `patternType` | string(20) | yes | `short_run`, `long_run`, or `interval` |
| `patternSignature` | string(500) | yes | Human-readable signature string |
| `patternSegments` | json | yes | Structured ordered segment array |
| `syncedAt` | datetime_immutable | no | When this record was last synced |

## Input Dependencies
- Task 01: Symfony project skeleton with Doctrine configured

## Output Artifacts
- `src/Entity/Activity.php` — mapped Doctrine entity
- `migrations/VersionXXX.php` — initial schema migration

## Implementation Notes

<details>
<summary>Detailed implementation guidance</summary>

1. **Generate the entity**: Use `bin/console make:entity Activity` (requires `symfony/maker-bundle`) or create the class manually in `src/Entity/Activity.php`.

2. **ORM mapping**: Use PHP 8 attributes (`#[ORM\Entity]`, `#[ORM\Column]`, etc.) for mapping. Set the table name to `activity`.

3. **JSON columns**: Doctrine's `json` column type maps PHP arrays to SQLite TEXT with automatic serialisation/deserialisation. Declare these as `?array` in PHP with `#[ORM\Column(type: 'json', nullable: true)]`.

4. **Unique constraint on stravaId**:
   ```php
   #[ORM\Column(type: 'bigint', unique: true)]
   private int $stravaId;
   ```

5. **Repository**: Create `src/Repository/ActivityRepository.php` extending `ServiceEntityRepository`. Add a `findByPatternSignature(string $signature): array` method for the dashboard grouping, and a `findLatestSyncedAt(): ?\DateTimeImmutable` method that returns the `activityDate` of the most recently synced record (used for incremental sync).

6. **Generate migration**: Run `bin/console doctrine:migrations:diff` to auto-generate the migration from the entity mapping, then `bin/console doctrine:migrations:migrate` to apply it.

7. **Validate**: Run `bin/console doctrine:schema:validate` — it should report "The mapping files are correct" and "The database schema is in sync with the mapping files."

</details>
