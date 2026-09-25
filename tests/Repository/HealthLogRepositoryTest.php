<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\HealthLog;
use App\Repository\HealthLogRepository;
use App\Tests\SchemaSetupTrait;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class HealthLogRepositoryTest extends KernelTestCase
{
    use SchemaSetupTrait;

    private HealthLogRepository $repo;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel(['environment' => 'test']);

        $this->setUpSchema();
        // Resolved through the container, not $this->em->getRepository(), so
        // the property gets the concrete type and the custom methods on
        // HealthLogRepository are visible to static analysis.
        $this->repo = static::getContainer()->get(HealthLogRepository::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownSchema();
        parent::tearDown();
        if (isset(static::$kernel)) {
            static::$kernel->shutdown();
        }
    }

    public function test_find_by_date_range_returns_empty_when_no_data(): void
    {
        $result = $this->repo->findByDateRange(null, null);
        self::assertIsArray($result);
        self::assertCount(0, $result);
    }

    public function test_find_by_date_range_respects_from_and_to(): void
    {
        // Insert three logs spanning a range
        $j = new HealthLog(new DateTimeImmutable('2025-01-05T12:00:00Z'));
        $j->setHeartRate(70);

        $mid = new HealthLog(new DateTimeImmutable('2025-03-15T14:00:00Z'));
        $mid->setSystolic(120)->setDiastolic(80);

        $july = new HealthLog(new DateTimeImmutable('2025-07-20T09:00:00Z'));
        $july->setWeight(180.0);

        $this->em->persist($j);
        $this->em->persist($mid);
        $this->em->persist($july);
        $this->em->flush();

        // Query only Q2 (Feb–Apr)
        $from = new DateTimeImmutable('2025-02-01T00:00:00Z');
        $to = new DateTimeImmutable('2025-04-30T23:59:59Z');

        $result = $this->repo->findByDateRange($from, $to);
        self::assertCount(1, $result);
        self::assertEquals($mid->getId(), current($result)->getId());
    }

    public function test_find_by_date_range_filters_by_emoji(): void
    {
        $a = new HealthLog();
        $a->setHeartRate(70)->setEmoji('😀');

        $b = new HealthLog();
        $b->setHeartRate(80)->setEmoji('🙂');

        $c = new HealthLog();
        $c->setHeartRate(90)->setEmoji('😀');

        $this->em->persist($a);
        $this->em->persist($b);
        $this->em->persist($c);
        $this->em->flush();

        $result = $this->repo->findByDateRange(null, null, ['😀']);
        self::assertCount(2, $result);

        foreach ($result as $log) {
            self::assertEquals('😀', $log->getEmoji());
        }
    }

    public function test_find_by_date_range_returns_descending_order(): void
    {
        $old = new HealthLog(new DateTimeImmutable('2025-01-01T00:00:00Z'));
        $old->setHeartRate(60);

        $newer = new HealthLog(new DateTimeImmutable('2025-06-01T00:00:00Z'));
        $newer->setHeartRate(80);

        $this->em->persist($old);
        $this->em->persist($newer);
        $this->em->flush();

        $result = $this->repo->findByDateRange(null, null);
        self::assertCount(2, $result);
        self::assertEquals('2025-06-01T00:00:00+00:00', $result[0]->getTimestamp()->format(DateTimeInterface::ATOM));
    }

    public function test_get_stats_for_date_range_returns_aggregates(): void
    {
        // Insert a few logs with known values
        $l1 = new HealthLog(new DateTimeImmutable('2025-04-01T00:00:00Z'));
        $l1->setSystolic(120)->setDiastolic(80)->setHeartRate(70);

        $l2 = new HealthLog(new DateTimeImmutable('2025-04-15T00:00:00Z'));
        $l2->setSystolic(130)->setDiastolic(85)->setHeartRate(90);

        $this->em->persist($l1);
        $this->em->persist($l2);
        $this->em->flush();

        $from = new DateTimeImmutable('2025-04-01T00:00:00Z');
        $to = new DateTimeImmutable('2025-04-30T23:59:59Z');

        $stats = $this->repo->getStatsForDateRange($from, $to);

        self::assertArrayHasKey('avgSystolic', $stats);
        self::assertArrayHasKey('minHeartRate', $stats);
        self::assertArrayHasKey('maxWeight', $stats); // null expected since no weights set

        // Average systolic of 120 and 130 = 125
        self::assertEqualsWithDelta(125.0, (float) $stats['avgSystolic'], 0.01);

        // Min heart rate should be 70
        self::assertEquals(70, (int) $stats['minHeartRate']);
    }

    public function test_get_stats_for_date_range_returns_empty_when_no_data(): void
    {
        $from = new DateTimeImmutable('2099-01-01T00:00:00Z');
        $to = new DateTimeImmutable('2099-12-31T23:59:59Z');

        $stats = $this->repo->getStatsForDateRange($from, $to);
        self::assertIsArray($stats);
    }
}
