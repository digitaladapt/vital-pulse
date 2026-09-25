<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\HealthLog;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class HealthLogTest extends TestCase
{
    public function test_constructor_defaults_timestamp_to_now(): void
    {
        $log = new HealthLog();
        self::assertInstanceOf(DateTimeImmutable::class, $log->getTimestamp());
    }

    public function test_constructor_accepts_custom_timestamp(): void
    {
        $ts = new DateTimeImmutable('2025-01-01T12:00:00Z');
        $log = new HealthLog($ts);
        self::assertSame($ts, $log->getTimestamp());
    }

    public function test_emoji_defaults_to_neutral(): void
    {
        $log = new HealthLog();
        self::assertEquals('😐', $log->getEmoji());
    }

    public function test_set_and_get_measurements(): void
    {
        $log = new HealthLog();
        $log->setSystolic(120);
        $log->setDiastolic(80);
        $log->setHeartRate(72);
        $log->setWeight(185.4);

        self::assertEquals(120, $log->getSystolic());
        self::assertEquals(80, $log->getDiastolic());
        self::assertEquals(72, $log->getHeartRate());
        self::assertEqualsWithDelta(185.4, $log->getWeight(), 0.01);
    }

    public function test_has_measurements_returns_true_when_any_value_set(): void
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

    public function test_has_measurements_returns_false_when_no_values_set(): void
    {
        $log = new HealthLog();
        self::assertFalse($log->hasMeasurements());
    }

    public function test_emoji_fallback_to_neutral_on_empty_string(): void
    {
        $log = new HealthLog();
        $log->setEmoji('');
        self::assertEquals('😐', $log->getEmoji());
    }

    public function test_id_is_null_before_persisting(): void
    {
        $log = new HealthLog();
        self::assertNull($log->getId());
    }

    public function test_timestamp_setter(): void
    {
        $log = new HealthLog();
        $newTs = new DateTimeImmutable('2025-06-15T09:30:00Z');
        $log->setTimestamp($newTs);
        self::assertEquals($newTs, $log->getTimestamp());
    }

    public function test_emoji_can_be_set_to_custom_value(): void
    {
        $log = new HealthLog();
        $log->setEmoji('😀');
        self::assertEquals('😀', $log->getEmoji());
    }
}
