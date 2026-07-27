<?php

namespace App\Tests\Entity;

use App\Entity\HealthLog;
use PHPUnit\Framework\TestCase;

class HealthLogTest extends TestCase
{
    public function testConstructorDefaultsTimestampToNow(): void
    {
        $log = new HealthLog();
        self::assertInstanceOf(\DateTimeImmutable::class, $log->getTimestamp());
    }

    public function testConstructorAcceptsCustomTimestamp(): void
    {
        $ts = new \DateTimeImmutable('2025-01-01T12:00:00Z');
        $log = new HealthLog($ts);
        self::assertSame($ts, $log->getTimestamp());
    }

    public function testEmojiDefaultsToNeutral(): void
    {
        $log = new HealthLog();
        self::assertEquals('😐', $log->getEmoji());
    }

    public function testSetAndGetMeasurements(): void
    {
        $log = new HealthLog();
        $log->setSystolic(120);
        $log->setDiastolic(80);
        $log->setHeartRate(72);
        $log->setWeight(185.4);

        self::assertEquals(120, $log->getSystolic());
        self::assertEquals(80, $log->getDiastolic());
        self::assertEquals(72, $log->getHeartRate());
        self::assertEquals(185.4, $log->getWeight(), 0.01);
    }

    public function testHasMeasurementsReturnsTrueWhenAnyValueSet(): void
    {
        $log = new HealthLog();
        $log->setSystolic(120);
        self::assertTrue($log->hasMeasurements());

        $log2 = new HealthLog();
        $log2->setHeartRate(80);
        self::assertTrue($log2->hasMeasurements());

        $log3 = new HealthLog();
        $log3->setWeight(170.5);
        self::assertTrue($log3->hasMeasurements());
    }

    public function testHasMeasurementsReturnsFalseWhenNoValuesSet(): void
    {
        $log = new HealthLog();
        self::assertFalse($log->hasMeasurements());
    }

    public function testEmojiFallbackToNeutralOnEmptyString(): void
    {
        $log = new HealthLog();
        $log->setEmoji('');
        self::assertEquals('😐', $log->getEmoji());
    }

    public function testIdIsNullBeforePersisting(): void
    {
        $log = new HealthLog();
        self::assertNull($log->getId());
    }

    public function testTimestampSetter(): void
    {
        $log = new HealthLog();
        $newTs = new \DateTimeImmutable('2025-06-15T09:30:00Z');
        $log->setTimestamp($newTs);
        self::assertEquals($newTs, $log->getTimestamp());
    }

    public function testEmojiCanBeSetToCustomValue(): void
    {
        $log = new HealthLog();
        $log->setEmoji('😀');
        self::assertEquals('😀', $log->getEmoji());
    }
}
