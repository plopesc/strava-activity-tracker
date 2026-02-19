<?php

namespace App\Command;

use App\Entity\Activity;
use App\Pattern\PatternRecognizer;
use App\Repository\ActivityRepository;
use App\Strava\StravaClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'strava:classify', description: 'Classify a Strava activity by its ID')]
class StravaClassifyCommand extends Command
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly StravaClient $stravaClient,
        private readonly PatternRecognizer $patternRecognizer,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('stravaId', InputArgument::REQUIRED, 'The Strava activity ID')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print result without persisting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stravaId = (int) $input->getArgument('stravaId');

        $activity = $this->activityRepository->findOneBy(['stravaId' => (string) $stravaId]);

        if ($activity === null) {
            try {
                $data = $this->stravaClient->getActivity($stravaId);
            } catch (\RuntimeException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            try {
                $streams = $this->stravaClient->getActivityStreams($stravaId);
            } catch (\RuntimeException $e) {
                $streams = [];
            }

            $activity = new Activity();
            $activity
                ->setStravaId($stravaId)
                ->setName($data['name'] ?? '')
                ->setActivityDate(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['start_date']))
                ->setDistance((float) ($data['distance'] ?? 0.0))
                ->setElapsedTime((int) ($data['elapsed_time'] ?? 0))
                ->setAverageSpeed((float) ($data['average_speed'] ?? 0.0))
                ->setAverageHeartrate(isset($data['average_heartrate']) ? (float) $data['average_heartrate'] : null)
                ->setMaxHeartrate(isset($data['max_heartrate']) ? (float) $data['max_heartrate'] : null)
                ->setSportType($data['sport_type'] ?? null)
                ->setRawLaps($data['laps'] ?? null)
                ->setRawStreams(!empty($streams) ? $streams : null)
                ->setSyncedAt(new \DateTimeImmutable());

            $this->em->persist($activity);
        }

        $this->patternRecognizer->classify($activity);

        $output->writeln('Type:      ' . ($activity->getPatternType() ?? 'null'));
        $output->writeln('Signature: ' . ($activity->getPatternSignature() ?? 'null'));
        $output->writeln('');
        $output->writeln('Segments:  ' . json_encode($activity->getPatternSegments(), JSON_PRETTY_PRINT));

        if ($input->getOption('dry-run')) {
            $output->writeln('Dry run — changes not persisted.');
            return Command::SUCCESS;
        }

        $this->em->flush();
        $output->writeln('Persisted.');

        return Command::SUCCESS;
    }
}
