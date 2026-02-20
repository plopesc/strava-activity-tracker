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
     * Returns paginated activities matching a given patternSignature with sorting support.
     *
     * @return array{activities: Activity[], total: int}
     */
    public function findByPatternSignaturePaginated(
        string $signature,
        int $page = 1,
        int $limit = 25,
        string $sort = 'date',
        string $direction = 'DESC',
    ): array {
        $allowedSorts = [
            'date' => 'a.activityDate',
            'name' => 'a.name',
            'distance' => 'a.distance',
            'pace' => 'a.averageSpeed',
            'duration' => 'a.elapsedTime',
            'hr' => 'a.averageHeartrate',
            'gear' => 'g.name',
        ];

        $sortColumn = $allowedSorts[$sort] ?? 'a.activityDate';
        $sortDir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.gear', 'g')
            ->addSelect('g')
            ->where('a.patternSignature = :sig')
            ->setParameter('sig', $signature)
            ->orderBy($sortColumn, $sortDir)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        /** @var Activity[] $activities */
        $activities = $qb->getQuery()->getResult();

        $total = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.patternSignature = :sig')
            ->setParameter('sig', $signature)
            ->getQuery()
            ->getSingleScalarResult();

        return ['activities' => $activities, 'total' => $total];
    }

    /**
     * Returns activities for a given year/month with optional pattern and gear filters.
     *
     * Eagerly loads the Gear relation.
     *
     * @return Activity[]
     */
    public function findByMonth(int $year, int $month, ?string $patternSignature = null, ?string $gear = null): array
    {
        $start = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $end = $start->modify('last day of this month')->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.gear', 'g')
            ->addSelect('g')
            ->where('a.activityDate BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.activityDate', 'ASC');

        if ($patternSignature !== null) {
            $qb->andWhere('a.patternSignature = :pattern')
                ->setParameter('pattern', $patternSignature);
        }

        if ($gear !== null) {
            $qb->andWhere('g.name = :gear')
                ->setParameter('gear', $gear);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns all distinct pattern signatures, sorted alphabetically.
     *
     * @return string[]
     */
    public function findDistinctPatternSignatures(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('DISTINCT a.patternSignature')
            ->where('a.patternSignature IS NOT NULL')
            ->orderBy('a.patternSignature', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'patternSignature');
    }

    /**
     * Returns all distinct gear names, sorted alphabetically.
     *
     * @return string[]
     */
    public function findDistinctGearNames(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('DISTINCT g.name')
            ->leftJoin('a.gear', 'g')
            ->where('g.name IS NOT NULL')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'name');
    }

    /**
     * Returns pattern groups with their recent activities.
     *
     * Each entry contains the pattern signature, total activity count, and up to $limit
     * most recent activities. Null-signature (unclassified) groups appear at the bottom.
     *
     * @return array<int, array{signature: null|string, count: int, activities: Activity[]}>
     */
    public function findPatternGroupsWithRecentActivities(int $limit = 5): array
    {
        $groups = $this->createQueryBuilder('a')
            ->select('a.patternSignature, COUNT(a.id) as activityCount')
            ->groupBy('a.patternSignature')
            ->orderBy('a.patternSignature', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($groups as $group) {
            $signature = $group['patternSignature'];
            $activities = $this->createQueryBuilder('a')
                ->leftJoin('a.gear', 'g')
                ->addSelect('g')
                ->where('a.patternSignature = :sig')
                ->setParameter('sig', $signature)
                ->orderBy('a.activityDate', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            $result[] = [
                'signature' => $signature,
                'count' => (int) $group['activityCount'],
                'activities' => $activities,
            ];
        }

        usort($result, static function (array $a, array $b): int {
            if ($a['signature'] === null) {
                return 1;
            }
            if ($b['signature'] === null) {
                return -1;
            }

            return strcasecmp((string) $a['signature'], (string) $b['signature']);
        });

        return $result;
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
