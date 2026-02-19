<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * Returns all activities grouped by patternSignature.
     *
     * Activities are fetched ordered by pattern_signature ASC, activity_date DESC,
     * then grouped in PHP into an array keyed by patternSignature (null key for unclassified).
     *
     * @return array<null|string, Activity[]>
     */
    public function findGroupedByPattern(): array
    {
        $activities = $this->createQueryBuilder('a')
            ->orderBy('a.patternSignature', 'ASC')
            ->addOrderBy('a.activityDate', 'DESC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($activities as $activity) {
            $key = $activity->getPatternSignature() ?: '__unclassified__';
            $grouped[$key][] = $activity;
        }

        return $grouped;
    }

    /**
     * Returns all activities matching a given patternSignature, ordered by activityDate ASC.
     *
     * @return Activity[]
     */
    public function findByPatternSignature(string $signature): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.patternSignature = :sig')
            ->setParameter('sig', $signature)
            ->orderBy('a.activityDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns the activityDate of the most recently synced activity, or null if none exist.
     */
    public function findLatestSyncedDate(): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('a')
            ->select('a.activityDate')
            ->orderBy('a.activityDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($result === null) {
            return null;
        }

        return $result['activityDate'];
    }
}
