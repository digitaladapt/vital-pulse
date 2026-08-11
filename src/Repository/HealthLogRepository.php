<?php

namespace App\Repository;

use App\Entity\HealthLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HealthLog>
 */
class HealthLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HealthLog::class);
    }

    /**
     * Fetch logs within a date range, optionally filtered by emoji.
     * Results are ordered by timestamp descending (newest first).
     */
    public function findByDateRange(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null, array $emojis = []): array
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.timestamp', 'DESC');

        if ($from instanceof \DateTimeInterface) {
            $qb->andWhere('l.timestamp >= :from')
               ->setParameter('from', $from);
        }

        if ($to instanceof \DateTimeInterface) {
            $qb->andWhere('l.timestamp <= :to')
               ->setParameter('to', $to);
        }

        if ($emojis !== []) {
            $qb->andWhere('l.emoji IN (:emojis)')
               ->setParameter('emojis', $emojis);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Aggregate stats for a given date range: averages and min/max of measurements.
     */
    public function getStatsForDateRange(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select(
                'AVG(l.systolic) AS avgSystolic',
                'MIN(l.systolic) AS minSystolic',
                'MAX(l.systolic) AS maxSystolic',
                'AVG(l.diastolic) AS avgDiastolic',
                'MIN(l.diastolic) AS minDiastolic',
                'MAX(l.diastolic) AS maxDiastolic',
                'AVG(l.heartRate) AS avgHeartRate',
                'MIN(l.heartRate) AS minHeartRate',
                'MAX(l.heartRate) AS maxHeartRate',
                'AVG(l.weight) AS avgWeight',
                'MIN(l.weight) AS minWeight',
                'MAX(l.weight) AS maxWeight'
            );

        if ($from instanceof \DateTimeInterface) {
            $qb->andWhere('l.timestamp >= :from')
               ->setParameter('from', $from);
        }

        if ($to instanceof \DateTimeInterface) {
            $qb->andWhere('l.timestamp <= :to')
               ->setParameter('to', $to);
        }

        return $qb->getQuery()->getOneOrNullResult() ?: [];
    }
}
