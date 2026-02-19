<?php

declare(strict_types=1);

namespace App\Tests\Pattern;

use App\Entity\Activity;
use App\Pattern\PatternRecognizer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class PatternRecognizerTest extends TestCase
{
    private PatternRecognizer $recognizer;

    protected function setUp(): void
    {
        $this->recognizer = new PatternRecognizer();
    }

    // -------------------------------------------------------------------------
    // Test 1: Short run classification
    // -------------------------------------------------------------------------

    public function testClassifyShortRun(): void
    {
        // 2700 samples near 3.5 m/s — very low variance
        $stream = array_fill(0, 2700, 3.5);
        // Add tiny noise at a few indices (still well within 10% CV)
        foreach ([100, 500, 900, 1300, 1700, 2100, 2500] as $i) {
            $stream[$i] = 3.52;
        }
        $rawStreams = ['velocity_smooth' => ['data' => $stream]];

        $activity = $this->makeActivity(9000.0, null, $rawStreams);
        $this->recognizer->classify($activity);

        self::assertSame('short_run', $activity->getPatternType());
        self::assertSame('short_run', $activity->getPatternSignature());
        self::assertNull($activity->getPatternSegments());
    }

    // -------------------------------------------------------------------------
    // Test 2: Long run classification
    // -------------------------------------------------------------------------

    public function testClassifyLongRun(): void
    {
        // 4500 samples near 3.2 m/s — very low variance
        $stream = array_fill(0, 4500, 3.2);
        // Add tiny noise at a few indices
        foreach ([200, 600, 1000, 1400, 1800, 2200, 2600, 3000, 3400, 3800, 4200] as $i) {
            $stream[$i] = 3.22;
        }
        $rawStreams = ['velocity_smooth' => ['data' => $stream]];

        $activity = $this->makeActivity(15000.0, null, $rawStreams);
        $this->recognizer->classify($activity);

        self::assertSame('long_run', $activity->getPatternType());
        self::assertSame('long_run', $activity->getPatternSignature());
        self::assertNull($activity->getPatternSegments());
    }

    // -------------------------------------------------------------------------
    // Test 3: Interval classification from laps (lap-based path)
    // -------------------------------------------------------------------------

    public function testClassifyIntervalFromLaps(): void
    {
        // 8 alternating laps: even = fast (1000m @ 4.2 m/s), odd = recovery (500m @ 2.5 m/s)
        // MAD of distances: mean = (1000+500)*4/8 = 750; deviations = |1000-750|=250 or |500-750|=250
        // MAD = 250 > lapMadThreshold(200) → lap path triggered
        $rawLaps = [];
        for ($i = 0; $i < 8; ++$i) {
            if ($i % 2 === 0) {
                $rawLaps[] = ['average_speed' => 4.2, 'distance' => 1000];
            } else {
                $rawLaps[] = ['average_speed' => 2.5, 'distance' => 500];
            }
        }

        // rawStreams = null forces the lap path
        $activity = $this->makeActivity(12000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        self::assertSame('interval', $activity->getPatternType());
        self::assertNotNull($activity->getPatternSegments());

        // Signature should contain 'fast' and/or 'recovery'
        $signature = $activity->getPatternSignature();
        self::assertNotNull($signature);
        self::assertNotEmpty($signature);
        self::assertTrue(
            str_contains($signature, 'fast') || str_contains($signature, 'recovery'),
            "Expected signature to contain 'fast' or 'recovery', got: {$signature}"
        );
    }

    // -------------------------------------------------------------------------
    // Test 4: Interval classification from stream (stream fallback)
    // -------------------------------------------------------------------------

    public function testClassifyIntervalFromStream(): void
    {
        // Single lap means trySegmentByLaps returns null (< 3 laps)
        $rawLaps = [['average_speed' => 3.5, 'distance' => 8000]];

        // Build interval stream:
        // 120s warmup at 3.0 m/s
        // 4 reps: 60s fast at 4.5 m/s + 30s recovery at 2.2 m/s
        // 120s cooldown at 3.0 m/s
        $data = array_fill(0, 120, 3.0);
        for ($i = 0; $i < 4; ++$i) {
            $data = array_merge($data, array_fill(0, 60, 4.5));
            $data = array_merge($data, array_fill(0, 30, 2.2));
        }
        $data = array_merge($data, array_fill(0, 120, 3.0));
        $rawStreams = ['velocity_smooth' => ['data' => $data]];

        $activity = $this->makeActivity(8000.0, $rawLaps, $rawStreams);
        $this->recognizer->classify($activity);

        self::assertSame('interval', $activity->getPatternType());
        self::assertNotNull($activity->getPatternSegments());
    }

    // -------------------------------------------------------------------------
    // Test 5: Pattern signature format for lap-based interval
    // -------------------------------------------------------------------------

    public function testIntervalSignatureFormat(): void
    {
        // Same lap setup as test 3
        $rawLaps = [];
        for ($i = 0; $i < 8; ++$i) {
            if ($i % 2 === 0) {
                $rawLaps[] = ['average_speed' => 4.2, 'distance' => 1000];
            } else {
                $rawLaps[] = ['average_speed' => 2.5, 'distance' => 500];
            }
        }

        $activity = $this->makeActivity(12000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $signature = $activity->getPatternSignature();

        self::assertNotNull($signature);
        self::assertNotEmpty($signature);
        self::assertStringNotContainsString('warmup', $signature);
        self::assertStringNotContainsString('cooldown', $signature);
        self::assertTrue(
            str_contains($signature, 'fast') || str_contains($signature, 'recovery'),
            "Expected signature to contain 'fast' or 'recovery', got: {$signature}"
        );
    }

    // -------------------------------------------------------------------------
    // Test 6: haveSamePattern — matching (within 10% tolerance)
    // -------------------------------------------------------------------------

    public function testHaveSamePatternMatching(): void
    {
        // Activity A: fast 1000m ×3, recovery 500m ×3
        $activityA = $this->makeIntervalActivity([
            ['type' => 'fast', 'distance_m' => 1000, 'count' => 3],
            ['type' => 'recovery', 'distance_m' => 500, 'count' => 3],
        ]);

        // Activity B: fast ~1048m ×3 (4.8% diff), recovery ~490m ×3 (2% diff) — both within 10%
        $activityB = $this->makeIntervalActivity([
            ['type' => 'fast', 'distance_m' => 1048, 'count' => 3],
            ['type' => 'recovery', 'distance_m' => 490, 'count' => 3],
        ]);

        self::assertTrue($this->recognizer->haveSamePattern($activityA, $activityB));
    }

    // -------------------------------------------------------------------------
    // Test 7: haveSamePattern — not matching (> 10% tolerance)
    // -------------------------------------------------------------------------

    public function testHaveSamePatternNotMatching(): void
    {
        // Activity A: fast 1000m ×3
        $activityA = $this->makeIntervalActivity([
            ['type' => 'fast', 'distance_m' => 1000, 'count' => 3],
        ]);

        // Activity B: fast 1200m ×3 — 20% difference, exceeds 10% tolerance
        $activityB = $this->makeIntervalActivity([
            ['type' => 'fast', 'distance_m' => 1200, 'count' => 3],
        ]);

        self::assertFalse($this->recognizer->haveSamePattern($activityA, $activityB));
    }

    // -------------------------------------------------------------------------
    // Test 8: haveSamePattern — short_run always matches
    // -------------------------------------------------------------------------

    public function testHaveSamePatternShortRunAlwaysMatches(): void
    {
        $activityA = new Activity();
        $activityA->setPatternType('short_run');
        $activityA->setPatternSignature('short_run');
        $activityA->setStravaId((string) random_int(1, 999999));
        $activityA->setName('Short Run A');
        $activityA->setDistance(9000.0);
        $activityA->setElapsedTime(2700);
        $activityA->setAverageSpeed(3.3);
        $activityA->setActivityDate(new \DateTimeImmutable());
        $activityA->setSyncedAt(new \DateTimeImmutable());

        $activityB = new Activity();
        $activityB->setPatternType('short_run');
        $activityB->setPatternSignature('short_run');
        $activityB->setStravaId((string) random_int(1, 999999));
        $activityB->setName('Short Run B');
        $activityB->setDistance(11000.0);
        $activityB->setElapsedTime(3300);
        $activityB->setAverageSpeed(3.3);
        $activityB->setActivityDate(new \DateTimeImmutable());
        $activityB->setSyncedAt(new \DateTimeImmutable());

        self::assertTrue($this->recognizer->haveSamePattern($activityA, $activityB));
    }

    // -------------------------------------------------------------------------
    // Test 9: haveSamePattern — null type returns false
    // -------------------------------------------------------------------------

    public function testHaveSamePatternNullTypeReturnsFalse(): void
    {
        $activityA = $this->makeIntervalActivity([
            ['type' => 'fast', 'distance_m' => 1000, 'count' => 4],
        ]);

        // Activity B has no pattern type set (null)
        $activityB = new Activity();
        $activityB->setPatternType(null);
        $activityB->setStravaId((string) random_int(1, 999999));
        $activityB->setName('No Pattern');
        $activityB->setDistance(8000.0);
        $activityB->setElapsedTime(3600);
        $activityB->setAverageSpeed(3.5);
        $activityB->setActivityDate(new \DateTimeImmutable());
        $activityB->setSyncedAt(new \DateTimeImmutable());

        self::assertFalse($this->recognizer->haveSamePattern($activityA, $activityB));
        // Also reversed
        self::assertFalse($this->recognizer->haveSamePattern($activityB, $activityA));
    }

    // -------------------------------------------------------------------------
    // Test 10: Null data → null pattern
    // -------------------------------------------------------------------------

    public function testClassifyNullDataReturnsNullPattern(): void
    {
        $activity = $this->makeActivity(10000.0, null, null);
        $this->recognizer->classify($activity);

        self::assertNull($activity->getPatternType());
        self::assertNull($activity->getPatternSignature());
        self::assertNull($activity->getPatternSegments());
    }

    // =========================================================================
    // Fixture helpers
    // =========================================================================

    /**
     * Creates an Activity with the given distance, rawLaps, and rawStreams.
     *
     * @param array<int|string, mixed>|null $rawLaps
     * @param array<string, mixed>|null $rawStreams
     */
    private function makeActivity(float $distanceM, ?array $rawLaps, ?array $rawStreams): Activity
    {
        $activity = new Activity();
        $activity->setDistance($distanceM);
        $activity->setRawLaps($rawLaps);
        $activity->setRawStreams($rawStreams);
        $activity->setElapsedTime(3600);
        $activity->setAverageSpeed(3.5);
        $activity->setStravaId((string) random_int(1, 999999));
        $activity->setName('Test Activity');
        $activity->setActivityDate(new \DateTimeImmutable());
        $activity->setSyncedAt(new \DateTimeImmutable());

        return $activity;
    }

    /**
     * Creates an Activity with patternType='interval' and specific training segments
     * (used for haveSamePattern tests).
     *
     * @param array<int, array<string, mixed>> $trainingSegments
     */
    private function makeIntervalActivity(array $trainingSegments): Activity
    {
        $activity = new Activity();
        $activity->setPatternType('interval');
        $activity->setPatternSignature('test');
        $activity->setPatternSegments($trainingSegments);
        $activity->setStravaId((string) random_int(1, 999999));
        $activity->setName('Test');
        $activity->setDistance(10000.0);
        $activity->setElapsedTime(3600);
        $activity->setAverageSpeed(3.5);
        $activity->setActivityDate(new \DateTimeImmutable());
        $activity->setSyncedAt(new \DateTimeImmutable());

        return $activity;
    }
}
