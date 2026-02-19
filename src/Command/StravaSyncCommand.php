<?php

namespace App\Command;

use App\Entity\Activity;
use App\Entity\Gear;
use App\Pattern\PatternRecognizer;
use App\Repository\ActivityRepository;
use App\Strava\AllowedSportType;
use App\Strava\StravaClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'strava:sync', description: 'Sync Strava running activities')]
class StravaSyncCommand extends Command
{
    public function __construct(
        private readonly StravaClient $stravaClient,
        private readonly PatternRecognizer $recognizer,
        private readonly ActivityRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('full', null, InputOption::VALUE_NONE, 'Re-fetch all activities');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $after = $input->getOption('full')
                ? null
                : $this->repo->findLatestSyncedDate()?->getTimestamp();

            $page = 1;
            $processed = 0;
            while (true) {
                $activities = $this->stravaClient->getActivities($page, 50, $after);
                if (empty($activities)) {
                    break;
                }
                foreach ($activities as $data) {
                    if (!in_array($data['sport_type'] ?? '', AllowedSportType::values(), true)) {
                        continue;
                    }
                    $this->processActivity($data, $output);
                    $processed++;
                    if ($processed % 20 === 0) {
                        $this->em->flush();
                        $this->em->clear();
                    }
                }
                $page++;
            }
            $this->em->flush();

            $output->writeln("Sync complete: {$processed} activities processed.");
            return Command::SUCCESS;
        } catch (\RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function processActivity(array $data, OutputInterface $output): void
    {
        $stravaId = (int) $data['id'];

        // Fetch detail + streams
        $detail = $this->stravaClient->getActivity($stravaId);
        $streams = $this->stravaClient->getActivityStreams($stravaId);

        // Upsert
        $activity = $this->repo->findOneBy(['stravaId' => $stravaId]) ?? new Activity();
        $gearRepo = $this->em->getRepository(Gear::class);

        // Map fields
        $activity
            ->setStravaId($stravaId)
            ->setName($data['name'])
            ->setActivityDate(new \DateTimeImmutable($data['start_date']))
            ->setDistance((float) $data['distance'])
            ->setElapsedTime((int) $data['elapsed_time'])
            ->setAverageSpeed((float) $data['average_speed'])
            ->setAverageHeartrate(isset($data['average_heartrate']) ? (float) $data['average_heartrate'] : null)
            ->setRawLaps($detail['laps'] ?? null)
            ->setRawStreams(!empty($streams) ? $streams : null)
            ->setSportType($detail['sport_type'] ?? null)
            ->setMaxHeartrate(isset($detail['max_heartrate']) ? (float) $detail['max_heartrate'] : null)
            ->setSyncedAt(new \DateTimeImmutable());

        // Gear upsert
        $gearData = $detail['gear'] ?? null;
        if ($gearData !== null && !empty($gearData['id'])) {
            $gear = $gearRepo->findOneBy(['stravaGearId' => $gearData['id']]);
            if (!$gear) {
                $gear = new Gear();
                $gear->setStravaGearId($gearData['id']);
                $gear->setName($gearData['name'] ?? 'Unknown');
                $this->em->persist($gear);
            }
            $activity->setGear($gear);
        } else {
            $activity->setGear(null);
        }

        // Classify
        $this->recognizer->classify($activity);

        $this->em->persist($activity);

        $sig = $activity->getPatternSignature() ?? 'unclassified';
        $output->writeln(sprintf(
            '[%s] %s → %s',
            $activity->getActivityDate()->format('Y-m-d'),
            $activity->getName(),
            $sig
        ));
    }
}
