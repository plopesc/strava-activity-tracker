<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Gear;
use App\Pattern\PatternRecognizer;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;

class ActivitySyncProcessor
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly PatternRecognizer $patternRecognizer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Process and persist a Strava activity from API data.
     *
     * @param array $data The activity data from Strava API
     * @param array|null $streams The activity streams data (optional)
     */
    public function process(array $data, ?array $streams = null): Activity
    {
        $stravaId = (int) ($data['id'] ?? 0);
        if ($stravaId === 0) {
            throw new \InvalidArgumentException('Activity ID not found in data');
        }

        // Upsert activity
        $activity = $this->activityRepository->findOneBy(['stravaId' => $stravaId])
            ?? new Activity();

        // Map core fields
        $activity
            ->setStravaId($stravaId)
            ->setName($data['name'] ?? $data['name'] ?? '')
            ->setActivityDate($this->parseDate($data['start_date'] ?? $data['start_date'] ?? null))
            ->setDistance((float) ($data['distance'] ?? $data['distance'] ?? 0.0))
            ->setElapsedTime((int) ($data['elapsed_time'] ?? $data['elapsed_time'] ?? 0))
            ->setAverageSpeed((float) ($data['average_speed'] ?? $data['average_speed'] ?? 0.0))
            ->setAverageHeartrate(isset($data['average_heartrate']) || isset($data['average_heartrate'])
                ? (float) ($data['average_heartrate'] ?? $data['average_heartrate'] ?? 0.0)
                : null)
            ->setMaxHeartrate(isset($data['max_heartrate'])
                ? (float) $data['max_heartrate']
                : null)
            ->setSportType($data['sport_type'] ?? null)
            ->setRawLaps($data['laps'] ?? null)
            ->setRawStreams(!empty($streams) ? $streams : null)
            ->setSyncedAt(new \DateTimeImmutable());

        // Handle gear
        $this->processGear($activity, $data);

        // Classify
        $this->patternRecognizer->classify($activity);

        $this->em->persist($activity);

        return $activity;
    }

    private function parseDate(?string $dateString): \DateTimeImmutable
    {
        if (!$dateString) {
            return new \DateTimeImmutable();
        }

        // Handle ISO 8601 format
        if (str_contains($dateString, 'T')) {
            return new \DateTimeImmutable($dateString);
        }

        // Fallback
        return \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dateString)
            ?: new \DateTimeImmutable();
    }

    private function processGear(Activity $activity, array $detail): void
    {
        $gearData = $detail['gear'] ?? null;
        if ($gearData === null || empty($gearData['id'])) {
            $activity->setGear(null);
            return;
        }

        $gearRepo = $this->em->getRepository(Gear::class);
        $gear = $gearRepo->findOneBy(['stravaGearId' => $gearData['id']]);

        if (!$gear) {
            $gear = new Gear();
            $gear->setStravaGearId($gearData['id']);
            $gear->setName($gearData['name'] ?? 'Unknown');
            $this->em->persist($gear);
        }

        $activity->setGear($gear);
    }
}
