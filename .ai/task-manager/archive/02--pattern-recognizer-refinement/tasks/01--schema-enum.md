---
id: 1
group: "schema"
dependencies: []
status: "completed"
created: 2026-02-19
skills:
  - php
  - database
---
# Create Gear entity, update Activity schema, add AllowedSportType enum, generate migration

## Objective
Introduce the `Gear` entity, add `gear`, `sportType`, and `maxHeartrate` to `Activity`, create the `AllowedSportType` backed enum, and generate + verify a Doctrine migration.

## Skills Required
PHP 8.4 (Doctrine ORM attributes, backed enum), Doctrine migrations

## Acceptance Criteria
- [ ] `src/Entity/Gear.php` exists with fields `id`, `stravaGearId` (string 50, unique), `name` (string 255)
- [ ] `Activity::$gear` is a nullable ManyToOne to `Gear` (join column `gear_id`, nullable)
- [ ] `Activity::$sportType` is a nullable string(50) column
- [ ] `Activity::$maxHeartrate` is a nullable float column
- [ ] `Activity` has `getGear/setGear`, `getSportType/setSportType`, `getMaxHeartrate/setMaxHeartrate` accessors
- [ ] `src/Strava/AllowedSportType.php` is a backed string enum with cases `Run`, `TrailRun`, `VirtualRun`, `UltraMarathon` and a static `values(): array` method
- [ ] A migration is generated via `bin/console doctrine:migrations:diff` and passes `bin/console doctrine:migrations:migrate --no-interaction`

## Technical Requirements

### `src/Entity/Gear.php`
```php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gear')]
class Gear {
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $stravaGearId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    // getters/setters
}
```

### Changes to `src/Entity/Activity.php`
Add three new mapped properties:
```php
#[ORM\ManyToOne(targetEntity: Gear::class)]
#[ORM\JoinColumn(nullable: true)]
private ?Gear $gear = null;

#[ORM\Column(type: 'string', length: 50, nullable: true)]
private ?string $sportType = null;

#[ORM\Column(type: 'float', nullable: true)]
private ?float $maxHeartrate = null;
```
Add getters and setters for all three.

### `src/Strava/AllowedSportType.php`
```php
namespace App\Strava;

enum AllowedSportType: string {
    case Run = 'Run';
    case TrailRun = 'TrailRun';
    case VirtualRun = 'VirtualRun';
    case UltraMarathon = 'UltraMarathon';

    public static function values(): array {
        return array_column(self::cases(), 'value');
    }
}
```

### Migration
Run inside DDEV:
```
ddev exec bin/console doctrine:migrations:diff
ddev exec bin/console doctrine:migrations:migrate --no-interaction
```

## Input Dependencies
None

## Output Artifacts
- `src/Entity/Gear.php`
- Updated `src/Entity/Activity.php`
- `src/Strava/AllowedSportType.php`
- New migration file under `migrations/`

## Implementation Notes

<details>
<summary>Key details</summary>

- `Gear` does NOT need a repository class — it will be looked up via `EntityManager::getRepository(Gear::class)->findOneBy(['stravaGearId' => ...])`.
- The `GearRepository` is not needed; the sync command will use `$em->getRepository(Gear::class)` directly.
- Keep `Gear` simple — no additional fields (brand, distance logged, etc.) are in scope.
- Run `ddev exec bin/console doctrine:mapping:info` to verify Doctrine picks up both entities before generating the migration.

</details>
