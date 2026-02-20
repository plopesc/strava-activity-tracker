<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ActivitySyncProcessor;
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
        private readonly StravaClient $stravaClient,
        private readonly ActivitySyncProcessor $processor,
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

        try {
            $detail = $this->stravaClient->getActivity($stravaId);
        } catch (\RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        try {
            $streams = $this->stravaClient->getActivityStreams($stravaId);
        } catch (\RuntimeException $e) {
            $streams = null;
        }

        // Process using the same approach as sync
        $activity = $this->processor->process($detail, $streams);

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
