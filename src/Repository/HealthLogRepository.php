<?php

declare(strict_types=1);

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
     *
     * @param int|null $limit  Max results to return (null = no limit)
     * @param int      $offset Number of results to skip
     */
    public function findByDateRange(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        array $emojis = [],
        ?int $limit = null,
        int $offset = 0,
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.timestamp', 'DESC')
            ->setFirstResult($offset);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

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
     * Count logs within a date range, optionally filtered by emoji.
     */
    public function countByDateRange(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        array $emojis = [],
    ): int {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)');

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

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get the min and max timestamps of logs within a date range.
     * Returns ['min' => DateTimeImmutable|null, 'max' => DateTimeImmutable|null].
     *
     * @return array{min: ?\DateTimeImmutable, max: ?\DateTimeImmutable}
     */
    public function getDateRangeBounds(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        array $emojis = [],
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->select('MIN(l.timestamp) AS minTs, MAX(l.timestamp) AS maxTs');

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

        $result = $qb->getQuery()->getOneOrNullResult();

        if ($result === null || $result['minTs'] === null) {
            return ['min' => null, 'max' => null];
        }

        return [
            'min' => $result['minTs'] instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromInterface($result['minTs'])
                : null,
            'max' => $result['maxTs'] instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromInterface($result['maxTs'])
                : null,
        ];
    }

    /**
     * Fetch aggregated logs grouped by day, week, or month.
     *
     * Uses SQLite's strftime() to bucket timestamps. Returns arrays
     * with avg/min/max per metric, plus the bucket key and count.
     *
     * @param string $interval 'day', 'week', or 'month'
     *
     * @return list<array<string, mixed>>
     */
    public function findAggregatedByDateRange(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        array $emojis = [],
        string $interval = 'day',
    ): array {
        $rsm = new \Doctrine\ORM\Query\ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addScalarResult('bucket', 'bucket');
        $rsm->addScalarResult('systolic_avg', 'systolic_avg');
        $rsm->addScalarResult('systolic_min', 'systolic_min');
        $rsm->addScalarResult('systolic_max', 'systolic_max');
        $rsm->addScalarResult('diastolic_avg', 'diastolic_avg');
        $rsm->addScalarResult('diastolic_min', 'diastolic_min');
        $rsm->addScalarResult('diastolic_max', 'diastolic_max');
        $rsm->addScalarResult('heart_rate_avg', 'heart_rate_avg');
        $rsm->addScalarResult('heart_rate_min', 'heart_rate_min');
        $rsm->addScalarResult('heart_rate_max', 'heart_rate_max');
        $rsm->addScalarResult('weight_avg', 'weight_avg');
        $rsm->addScalarResult('weight_min', 'weight_min');
        $rsm->addScalarResult('weight_max', 'weight_max');
        $rsm->addScalarResult('count', 'count');

        // Build the strftime format for the bucket key
        $fmt = match ($interval) {
            'day'   => '%Y-%m-%d',
            'week'  => '%Y-W%W',
            'month' => '%Y-%m',
        };

        $sql = "SELECT strftime('{$fmt}', l.timestamp) AS bucket, "
            . 'AVG(l.systolic) AS systolic_avg, '
            . 'MIN(l.systolic) AS systolic_min, '
            . 'MAX(l.systolic) AS systolic_max, '
            . 'AVG(l.diastolic) AS diastolic_avg, '
            . 'MIN(l.diastolic) AS diastolic_min, '
            . 'MAX(l.diastolic) AS diastolic_max, '
            . 'AVG(l.heart_rate) AS heart_rate_avg, '
            . 'MIN(l.heart_rate) AS heart_rate_min, '
            . 'MAX(l.heart_rate) AS heart_rate_max, '
            . 'AVG(l.weight) AS weight_avg, '
            . 'MIN(l.weight) AS weight_min, '
            . 'MAX(l.weight) AS weight_max, '
            . 'COUNT(l.id) AS count '
            . 'FROM health_log l ';

        $where = [];
        $params = [];
        $paramIndex = 1;

        if ($from instanceof \DateTimeInterface) {
            $where[] = 'l.timestamp >= ?' . $paramIndex;
            $params[$paramIndex] = $from->format('Y-m-d H:i:s');
            ++$paramIndex;
        }

        if ($to instanceof \DateTimeInterface) {
            $where[] = 'l.timestamp <= ?' . $paramIndex;
            $params[$paramIndex] = $to->format('Y-m-d H:i:s');
            ++$paramIndex;
        }

        if ($emojis !== []) {
            $placeholders = [];
            foreach ($emojis as $emoji) {
                $placeholders[] = '?' . $paramIndex;
                $params[$paramIndex] = $emoji;
                ++$paramIndex;
            }
            $where[] = 'l.emoji IN (' . implode(', ', $placeholders) . ')';
        }

        if ($where !== []) {
            $sql .= 'WHERE ' . implode(' AND ', $where) . ' ';
        }

        $sql .= 'GROUP BY bucket ORDER BY bucket ASC';

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        foreach ($params as $index => $value) {
            $query->setParameter($index, $value);
        }

        return $query->getResult();
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
