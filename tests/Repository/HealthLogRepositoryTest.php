<?php

namespace App\Tests\Repository;

use App\Entity\HealthLog;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class HealthLogRepositoryTest extends KernelTestCase
{
    private \Doctrine\ORM\EntityManagerInterface $em;
    private \App\Repository\HealthLogRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel(['environment' => 'test']);

        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repo = $this->em->getRepository(HealthLog::class);

        // Build schema for in-memory SQLite (clean each test)
        $metadataFactory = $this->em->getMetadataFactory();
        $classes = array_filter($metadataFactory->getAllMetadata(), fn ($m) => $m->getName() === HealthLog::class);
        $schemaTool = new SchemaTool($this->em);
        if ($schemaTool->getSchemaFromMetadata($classes)->getTables()) {
            $schemaTool->dropDatabase(array_values($classes));
        }
        $schemaTool->createSchema(array_values($classes));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (isset(static::$kernel)) {
            static::$kernel->shutdown();
        }
    }

    public function testFindByDateRangeReturnsEmptyWhenNoData(): void
    {
        $result = $this->repo->findByDateRange(null, null);
        self::assertIsArray($result);
        self::assertCount(0, $result);
    }

    public function testFindByDateRangeRespectsFromAndTo(): void
    {
        // Insert three logs spanning a range
        $j = new HealthLog(new \DateTimeImmutable('2025-01-05T12:00:00Z'));
        $j->setHeartRate(70);

        $mid = new HealthLog(new \DateTimeImmutable('2025-03-15T14:00:00Z'));
        $mid->setSystolic(120)->setDiastolic(80);

        $july = new HealthLog(new \DateTimeImmutable('2025-07-20T09:00:00Z'));
        $july->setWeight(180.0);

        $this->em->persist($j);
        $this->em->persist($mid);
        $this->em->persist($july);
        $this->em->flush();

        // Query only Q2 (Feb–Apr)
        $from = new \DateTimeImmutable('2025-02-01T00:00:00Z');
        $to = new \DateTimeImmutable('2025-04-30T23:59:59Z');

        $result = $this->repo->findByDateRange($from, $to);
        self::assertCount(1, $result);
        self::assertEquals($mid->getId(), current($result)->getId());
    }

    public function testFindByDateRangeFiltersByEmoji(): void
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

        $result = $this->repo->findByDateRange(null, null, '😀');
        self::assertCount(2, $result);

        foreach ($result as $log) {
            self::assertEquals('😀', $log->getEmoji());
        }
    }

    public function testFindByDateRangeReturnsDescendingOrder(): void
    {
        $old = new HealthLog(new \DateTimeImmutable('2025-01-01T00:00:00Z'));
        $old->setHeartRate(60);

        $newer = new HealthLog(new \DateTimeImmutable('2025-06-01T00:00:00Z'));
        $newer->setHeartRate(80);

        $this->em->persist($old);
        $this->em->persist($newer);
        $this->em->flush();

        $result = $this->repo->findByDateRange(null, null);
        self::assertCount(2, $result);
        self::assertEquals('2025-06-01T00:00:00+00:00', $result[0]->getTimestamp()->format(\DateTimeInterface::ATOM));
    }

    public function testGetStatsForDateRangeReturnsAggregates(): void
    {
        // Insert a few logs with known values
        $l1 = new HealthLog(new \DateTimeImmutable('2025-04-01T00:00:00Z'));
        $l1->setSystolic(120)->setDiastolic(80)->setHeartRate(70);

        $l2 = new HealthLog(new \DateTimeImmutable('2025-04-15T00:00:00Z'));
        $l2->setSystolic(130)->setDiastolic(85)->setHeartRate(90);

        $this->em->persist($l1);
        $this->em->persist($l2);
        $this->em->flush();

        $from = new \DateTimeImmutable('2025-04-01T00:00:00Z');
        $to = new \DateTimeImmutable('2025-04-30T23:59:59Z');

        $stats = $this->repo->getStatsForDateRange($from, $to);

        self::assertArrayHasKey('avgSystolic', $stats);
        self::assertArrayHasKey('minHeartRate', $stats);
        self::assertArrayHasKey('maxWeight', $stats); // null expected since no weights set

        // Average systolic of 120 and 130 = 125
        self::assertEqualsWithDelta(125.0, (float) $stats['avgSystolic'], 0.01);

        // Min heart rate should be 70
        self::assertEquals(70, (int) $stats['minHeartRate']);
    }

    public function testGetStatsForDateRangeReturnsEmptyWhenNoData(): void
    {
        $from = new \DateTimeImmutable('2099-01-01T00:00:00Z');
        $to = new \DateTimeImmutable('2099-12-31T23:59:59Z');

        $stats = $this->repo->getStatsForDateRange($from, $to);
        self::assertIsArray($stats);
    }
}
