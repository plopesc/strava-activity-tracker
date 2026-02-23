<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Activity;
use App\Entity\Gear;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $gear1 = $this->createGear($manager, 'g_pegasus40', 'Nike Pegasus 40');
        $gear2 = $this->createGear($manager, 'g_nimbus25', 'ASICS Gel-Nimbus 25');
        $gear3 = $this->createGear($manager, 'g_ghost15', 'Brooks Ghost 15');

        $stravaId = 1000000001;
        $syncedAt = new \DateTimeImmutable('2026-01-15 12:00:00');

        // === Pattern group: "easy 9km" (steady, 6 activities across months) ===
        $this->createActivity($manager, (string) $stravaId++, 'Easy Morning 9km', '2025-10-05 07:30:00', 9000, 3240, 2.78, 142, 158, 'steady', 'easy 9km', $gear1, $this->fullStreams(), $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Easy Recovery 9km', '2025-10-12 08:00:00', 9100, 3300, 2.76, 140, 155, 'steady', 'easy 9km', $gear1, $this->fullStreams(), $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Easy Afternoon 9km', '2025-11-03 16:00:00', 8950, 3200, 2.80, 144, 160, 'steady', 'easy 9km', $gear2, $this->fullStreams(), $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Easy Weekend 9km', '2025-11-15 09:00:00', 9050, 3260, 2.78, 141, 156, 'steady', 'easy 9km', $gear1, $this->partialStreamsNoHr(), $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Easy Christmas 9km', '2025-12-25 10:00:00', 9200, 3360, 2.74, 143, 159, 'steady', 'easy 9km', $gear2, $this->fullStreams(), $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Easy New Year 9km', '2026-01-02 08:30:00', 9100, 3280, 2.77, 139, 154, 'steady', 'easy 9km', $gear1, $this->fullStreams(), $syncedAt);

        // === Pattern group: "3x1km intervals" (interval, 3 activities) ===
        $this->createActivity($manager, (string) $stravaId++, 'Tuesday Intervals 3x1km', '2025-10-08 18:00:00', 7500, 2700, 2.78, 165, 182, 'interval', '3x1km intervals', $gear3, $this->fullStreams(), $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Track Session 3x1km', '2025-11-05 18:30:00', 7600, 2750, 2.76, 168, 185, 'interval', '3x1km intervals', $gear3, $this->partialStreamsNoLatlng(), $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Speed Work 3x1km', '2025-12-10 17:45:00', 7400, 2650, 2.79, 166, 183, 'interval', '3x1km intervals', $gear3, $this->fullStreams(), $syncedAt);

        // === Pattern group: "long sunday run" (long_run, 2 activities) ===
        $this->createActivity($manager, (string) $stravaId++, 'Sunday Long Run', '2025-10-19 08:00:00', 18000, 6480, 2.78, 148, 165, 'long_run', 'long sunday run', $gear1, $this->fullStreams(), $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Sunday Endurance Run', '2025-11-23 08:30:00', 19000, 6840, 2.78, 150, 168, 'long_run', 'long sunday run', $gear2, $this->fullStreams(), $syncedAt);

        // === Pattern group: "5km tempo" (steady, 2 activities) ===
        $this->createActivity($manager, (string) $stravaId++, 'Tempo 5km', '2025-11-10 07:00:00', 5000, 1500, 3.33, 170, 185, 'steady', '5km tempo', $gear3, $this->partialStreamsNoHr(), $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Fast Tempo 5km', '2025-12-08 07:15:00', 5100, 1480, 3.45, 172, 188, 'steady', '5km tempo', $gear3, $this->fullStreams(), $syncedAt);

        // === Unclassified activities (no pattern type/signature) ===
        $this->createActivity($manager, (string) $stravaId++, 'Random Easy Run', '2025-10-22 12:00:00', 6000, 2400, 2.50, 135, 150, null, null, $gear2, null, $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Exploration Run', '2025-11-18 15:00:00', 4500, 1800, 2.50, null, null, null, null, null, null, $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Quick Jog', '2025-12-01 06:30:00', 3000, 1200, 2.50, 130, 145, null, null, $gear1, $this->partialStreamsNoLatlng(), $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Treadmill Run', '2026-01-10 19:00:00', 5500, 2200, 2.50, 140, 155, null, null, null, null, $syncedAt);

        // === Extra activities for empty-month contrast and edge cases ===
        $this->createActivity($manager, (string) $stravaId++, 'Short Recovery Run', '2025-10-30 16:00:00', 4000, 1600, 2.50, 128, 140, 'short_run', 'short 4km', $gear2, $this->fullStreams(), $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'Hill Repeats', '2025-12-15 07:00:00', 8000, 3000, 2.67, 160, 178, 'interval', 'hill repeats', $gear1, $this->fullStreams(), $syncedAt);
        $this->createActivity($manager, (string) $stravaId++, 'New Year Resolution Run', '2026-01-05 10:00:00', 10000, 3600, 2.78, 145, 162, 'long_run', 'long sunday run', $gear1, $this->fullStreams(), $syncedAt);

        // Activity with no streams and no HR for detail page degradation test
        $this->createActivity($manager, (string) $stravaId++, 'No Data Run', '2025-12-20 14:00:00', 7000, 2800, 2.50, null, null, 'steady', 'easy 9km', null, null, $syncedAt);

        // Activity with velocity_smooth but no heartrate and no latlng
        $this->createActivity($manager, (string) $stravaId++, 'Pace Only Run', '2025-11-28 11:00:00', 6500, 2600, 2.50, null, null, 'steady', 'easy 9km', $gear3, $this->partialStreamsNoLatlngNoHr(), $syncedAt);

        $manager->flush();
    }

    private function createGear(ObjectManager $manager, string $stravaGearId, string $name): Gear
    {
        $gear = new Gear();
        $gear->setStravaGearId($stravaGearId);
        $gear->setName($name);
        $manager->persist($gear);

        return $gear;
    }

    /** @param null|array<string, array<string, list<mixed>>> $rawStreams */
    private function createActivity(
        ObjectManager $manager,
        string $stravaId,
        string $name,
        string $date,
        float $distance,
        int $elapsedTime,
        float $averageSpeed,
        ?float $avgHr,
        ?float $maxHr,
        ?string $patternType,
        ?string $patternSignature,
        ?Gear $gear,
        ?array $rawStreams,
        \DateTimeImmutable $syncedAt,
    ): void {
        $activity = new Activity();
        $activity->setStravaId($stravaId);
        $activity->setName($name);
        $activity->setActivityDate(new \DateTimeImmutable($date));
        $activity->setDistance($distance);
        $activity->setElapsedTime($elapsedTime);
        $activity->setAverageSpeed($averageSpeed);
        $activity->setAverageHeartrate($avgHr);
        $activity->setMaxHeartrate($maxHr);
        $activity->setPatternType($patternType);
        $activity->setPatternSignature($patternSignature);
        $activity->setGear($gear);
        $activity->setSportType('Run');
        $activity->setRawStreams($rawStreams);
        $activity->setSyncedAt($syncedAt);
        $manager->persist($activity);
    }

    /** @return array<string, array<string, list<mixed>>> */
    private function fullStreams(): array
    {
        return [
            'velocity_smooth' => ['data' => [2.5, 2.6, 2.7, 2.8, 2.7, 2.6, 2.5, 2.8, 2.9, 2.7]],
            'distance' => ['data' => [0, 100, 200, 300, 400, 500, 600, 700, 800, 900]],
            'heartrate' => ['data' => [120, 125, 130, 135, 140, 142, 138, 136, 140, 138]],
            'latlng' => ['data' => [
                [41.3800, 2.1700], [41.3810, 2.1710], [41.3820, 2.1720],
                [41.3830, 2.1730], [41.3840, 2.1740], [41.3850, 2.1750],
                [41.3860, 2.1760], [41.3870, 2.1770], [41.3880, 2.1780],
                [41.3890, 2.1790],
            ]],
        ];
    }

    /** @return array<string, array<string, list<mixed>>> */
    private function partialStreamsNoHr(): array
    {
        return [
            'velocity_smooth' => ['data' => [2.5, 2.6, 2.7, 2.8, 2.7, 2.6, 2.5, 2.8, 2.9, 2.7]],
            'distance' => ['data' => [0, 100, 200, 300, 400, 500, 600, 700, 800, 900]],
            'latlng' => ['data' => [
                [41.3800, 2.1700], [41.3810, 2.1710], [41.3820, 2.1720],
                [41.3830, 2.1730], [41.3840, 2.1740], [41.3850, 2.1750],
                [41.3860, 2.1760], [41.3870, 2.1770], [41.3880, 2.1780],
                [41.3890, 2.1790],
            ]],
        ];
    }

    /** @return array<string, array<string, list<mixed>>> */
    private function partialStreamsNoLatlng(): array
    {
        return [
            'velocity_smooth' => ['data' => [2.5, 2.6, 2.7, 2.8, 2.7, 2.6, 2.5, 2.8, 2.9, 2.7]],
            'distance' => ['data' => [0, 100, 200, 300, 400, 500, 600, 700, 800, 900]],
            'heartrate' => ['data' => [120, 125, 130, 135, 140, 142, 138, 136, 140, 138]],
        ];
    }

    /** @return array<string, array<string, list<mixed>>> */
    private function partialStreamsNoLatlngNoHr(): array
    {
        return [
            'velocity_smooth' => ['data' => [2.5, 2.6, 2.7, 2.8, 2.7, 2.6, 2.5, 2.8, 2.9, 2.7]],
            'distance' => ['data' => [0, 100, 200, 300, 400, 500, 600, 700, 800, 900]],
        ];
    }
}
