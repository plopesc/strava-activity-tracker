<?php

namespace App\Tests\Pattern;

use App\Entity\Activity;
use App\Pattern\PatternRecognizer;
use PHPUnit\Framework\TestCase;

class PatternRecognizerTest extends TestCase
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

        $this->assertSame('short_run', $activity->getPatternType());
        $this->assertSame('easy 9km', $activity->getPatternSignature()); // 9000m → floor(9) = 9
        $this->assertNotNull($activity->getPatternSegments());
        $segments = $activity->getPatternSegments();
        $this->assertCount(1, $segments);
        $this->assertSame('easy', $segments[0]['type']);
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

        $this->assertSame('long_run', $activity->getPatternType());
        $this->assertSame('easy 15km', $activity->getPatternSignature()); // 15000m → floor(15) = 15
        $this->assertNotNull($activity->getPatternSegments());
        $segments = $activity->getPatternSegments();
        $this->assertCount(1, $segments);
        $this->assertSame('easy', $segments[0]['type']);
    }

    // -------------------------------------------------------------------------
    // Test 3: Interval classification from laps (lap-based path)
    // -------------------------------------------------------------------------

    public function testClassifyIntervalFromLaps(): void
    {
        // 8 alternating laps: even = fast (1000m @ 4.2 m/s), odd = recovery (500m @ 2.5 m/s)
        // MAD of distances: mean = (1000+500)*4/8 = 750; deviations = |1000-750|=250 or |500-750|=250
        $rawLaps = [];
        for ($i = 0; $i < 8; $i++) {
            if ($i % 2 === 0) {
                $rawLaps[] = ['average_speed' => 4.2, 'distance' => 1000];
            } else {
                $rawLaps[] = ['average_speed' => 2.5, 'distance' => 500];
            }
        }

        // rawStreams = null forces the lap path
        $activity = $this->makeActivity(12000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $this->assertSame('interval', $activity->getPatternType());
        $this->assertNotNull($activity->getPatternSegments());

        // Signature should be non-empty and in the new distance-only format (e.g. "4×1km")
        $signature = $activity->getPatternSignature();
        $this->assertNotEmpty($signature);
    }

    // -------------------------------------------------------------------------
    // Test 4: Interval classification from stream (stream fallback)
    // -------------------------------------------------------------------------

    public function testClassifyIntervalFromStream(): void
    {
        // 8 laps with varying distances to trigger interval classification
        // Warm lap (500m @ 3.0 m/s), 4 reps: fast (600m @ 4.5 m/s) + recovery (400m @ 2.2 m/s), cool lap (500m @ 3.0 m/s)
        $rawLaps = [
            ['average_speed' => 3.0, 'distance' => 500],  // warmup
            ['average_speed' => 4.5, 'distance' => 600],  // fast
            ['average_speed' => 2.2, 'distance' => 400],  // recovery
            ['average_speed' => 4.5, 'distance' => 600],  // fast
            ['average_speed' => 2.2, 'distance' => 400],  // recovery
            ['average_speed' => 4.5, 'distance' => 600],  // fast
            ['average_speed' => 2.2, 'distance' => 400],  // recovery
            ['average_speed' => 4.5, 'distance' => 600],  // fast
            ['average_speed' => 2.2, 'distance' => 400],  // recovery
            ['average_speed' => 3.0, 'distance' => 500],  // cooldown
        ];

        $activity = $this->makeActivity(6000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $this->assertSame('interval', $activity->getPatternType());
        $this->assertNotNull($activity->getPatternSegments());
    }

    // -------------------------------------------------------------------------
    // Test 5: Pattern signature format for lap-based interval
    // -------------------------------------------------------------------------

    public function testIntervalSignatureFormat(): void
    {
        // Same lap setup as test 3
        $rawLaps = [];
        for ($i = 0; $i < 8; $i++) {
            if ($i % 2 === 0) {
                $rawLaps[] = ['average_speed' => 4.2, 'distance' => 1000];
            } else {
                $rawLaps[] = ['average_speed' => 2.5, 'distance' => 500];
            }
        }

        $activity = $this->makeActivity(12000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $signature = $activity->getPatternSignature();

        $this->assertNotEmpty($signature);
        $this->assertStringNotContainsString('warmup', $signature);
        $this->assertStringNotContainsString('cooldown', $signature);
        // New format: "4×1km" (distance-only, no type labels, recovery excluded)
        $this->assertSame('4×1km', $signature);
    }

    // -------------------------------------------------------------------------
    // Test 6: haveSamePattern — matching (within 10% tolerance)
    // -------------------------------------------------------------------------

    public function testHaveSamePatternMatching(): void
    {
        // Activity A: fast 1000m ×3, recovery 500m ×3
        $activityA = $this->makeIntervalActivity([
            ['type' => 'fast',     'distance_m' => 1000, 'count' => 3],
            ['type' => 'recovery', 'distance_m' => 500,  'count' => 3],
        ]);

        // Activity B: fast ~1048m ×3 (4.8% diff), recovery ~490m ×3 (2% diff) — both within 10%
        $activityB = $this->makeIntervalActivity([
            ['type' => 'fast',     'distance_m' => 1048, 'count' => 3],
            ['type' => 'recovery', 'distance_m' => 490,  'count' => 3],
        ]);

        $this->assertTrue($this->recognizer->haveSamePattern($activityA, $activityB));
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

        $this->assertFalse($this->recognizer->haveSamePattern($activityA, $activityB));
    }

    // -------------------------------------------------------------------------
    // Test 8: haveSamePattern — short_run always matches
    // -------------------------------------------------------------------------

    public function testHaveSamePatternShortRunAlwaysMatches(): void
    {
        $activityA = new Activity();
        $activityA->setPatternType('short_run');
        $activityA->setPatternSignature('short_run');
        $activityA->setStravaId(random_int(1, 999999));
        $activityA->setName('Short Run A');
        $activityA->setDistance(9000.0);
        $activityA->setElapsedTime(2700);
        $activityA->setAverageSpeed(3.3);
        $activityA->setActivityDate(new \DateTimeImmutable());
        $activityA->setSyncedAt(new \DateTimeImmutable());

        $activityB = new Activity();
        $activityB->setPatternType('short_run');
        $activityB->setPatternSignature('short_run');
        $activityB->setStravaId(random_int(1, 999999));
        $activityB->setName('Short Run B');
        $activityB->setDistance(11000.0);
        $activityB->setElapsedTime(3300);
        $activityB->setAverageSpeed(3.3);
        $activityB->setActivityDate(new \DateTimeImmutable());
        $activityB->setSyncedAt(new \DateTimeImmutable());

        $this->assertTrue($this->recognizer->haveSamePattern($activityA, $activityB));
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
        $activityB->setStravaId(random_int(1, 999999));
        $activityB->setName('No Pattern');
        $activityB->setDistance(8000.0);
        $activityB->setElapsedTime(3600);
        $activityB->setAverageSpeed(3.5);
        $activityB->setActivityDate(new \DateTimeImmutable());
        $activityB->setSyncedAt(new \DateTimeImmutable());

        $this->assertFalse($this->recognizer->haveSamePattern($activityA, $activityB));
        // Also reversed
        $this->assertFalse($this->recognizer->haveSamePattern($activityB, $activityA));
    }

    // -------------------------------------------------------------------------
    // Test 10: Null data → null pattern
    // -------------------------------------------------------------------------

    public function testClassifyNullDataReturnsNullPattern(): void
    {
        $activity = $this->makeActivity(10000.0, null, null);
        $this->recognizer->classify($activity);

        $this->assertNull($activity->getPatternType());
        $this->assertNull($activity->getPatternSignature());
        $this->assertNull($activity->getPatternSegments());
    }

    // -------------------------------------------------------------------------
    // Test 11: Easy run segment has stats
    // -------------------------------------------------------------------------

    public function testEasyRunSegmentHasStats(): void
    {
        $stream = array_fill(0, 2700, 3.5);
        $rawStreams = ['velocity_smooth' => ['data' => $stream]];

        $activity = $this->makeActivity(9500.0, null, $rawStreams);
        $activity->setAverageSpeed(3.5);
        $activity->setAverageHeartrate(140.0);
        $activity->setMaxHeartrate(165.0);
        $this->recognizer->classify($activity);

        $this->assertSame('short_run', $activity->getPatternType());
        $this->assertSame('easy 9km', $activity->getPatternSignature()); // floor(9.5) = 9
        $segments = $activity->getPatternSegments();
        $this->assertNotNull($segments);
        $this->assertCount(1, $segments);
        $this->assertSame('easy', $segments[0]['type']);
        $this->assertSame(9000.0, $segments[0]['distance_m']);
        $this->assertArrayHasKey('avg_speed', $segments[0]);
        $this->assertArrayHasKey('avg_heartrate', $segments[0]);
        $this->assertArrayHasKey('max_heartrate', $segments[0]);
    }

    // -------------------------------------------------------------------------
    // Test 12: Interval signature "2×6km"
    // -------------------------------------------------------------------------

    public function testIntervalSignature2x6km(): void
    {
        // Laps: warmup(2km@3.2), recovery(0.5km@2.5), moderate(6km@3.4), recovery(1km@2.5),
        //       moderate(6km@3.4), recovery(0.5km@2.5), cooldown(2km@3.2)
        // Speeds: [3.2, 2.5, 3.4, 2.5, 3.4, 2.5, 3.2] → sorted = [2.5,2.5,2.5,3.2,3.2,3.4,3.4] → median = 3.2
        // 1.15*3.2=3.68, 0.85*3.2=2.72
        // 3.2 → moderate, 2.5<2.72 → recovery, 3.4<3.68 → moderate
        // After mergeSameType: [moderate(2km), recovery(0.5km), moderate(6km), recovery(1km),
        //                        moderate(6km), recovery(0.5km), moderate(2km)]
        // After applyWarmupCooldown: [warmup(2km), recovery, moderate(6km), recovery, moderate(6km), recovery, cooldown(2km)]
        // extractTrainingSegments(fast+moderate): [moderate(6km), moderate(6km)]
        // mergeTrainingSegmentsByTypeAndDistance: key=moderate_6000, count=2, dist=12000 → "2×6km"
        $rawLaps = [
            ['average_speed' => 3.2, 'distance' => 2000],  // warmup
            ['average_speed' => 2.5, 'distance' => 500],   // recovery
            ['average_speed' => 3.4, 'distance' => 6000],  // moderate (training)
            ['average_speed' => 2.5, 'distance' => 1000],  // recovery
            ['average_speed' => 3.4, 'distance' => 6000],  // moderate (training)
            ['average_speed' => 2.5, 'distance' => 500],   // recovery
            ['average_speed' => 3.2, 'distance' => 2000],  // cooldown
        ];

        $activity = $this->makeActivity(18000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $this->assertSame('interval', $activity->getPatternType());
        $this->assertSame('2×6km', $activity->getPatternSignature());
    }

    // =========================================================================
    // Fixture helpers
    // =========================================================================

    /**
     * Creates an Activity with the given distance, rawLaps, and rawStreams.
     */
    private function makeActivity(float $distanceM, ?array $rawLaps, ?array $rawStreams): Activity
    {
        $activity = new Activity();
        $activity->setDistance($distanceM);
        $activity->setRawLaps($rawLaps);
        $activity->setRawStreams($rawStreams);
        $activity->setElapsedTime(3600);
        $activity->setAverageSpeed(3.5);
        $activity->setStravaId(random_int(1, 999999));
        $activity->setName('Test Activity');
        $activity->setActivityDate(new \DateTimeImmutable());
        $activity->setSyncedAt(new \DateTimeImmutable());

        return $activity;
    }

    /**
     * Creates an Activity with patternType='interval' and specific training segments
     * (used for haveSamePattern tests).
     */
    private function makeIntervalActivity(array $trainingSegments): Activity
    {
        $activity = new Activity();
        $activity->setPatternType('interval');
        $activity->setPatternSignature('test');
        $activity->setPatternSegments($trainingSegments);
        $activity->setStravaId(random_int(1, 999999));
        $activity->setName('Test');
        $activity->setDistance(10000.0);
        $activity->setElapsedTime(3600);
        $activity->setAverageSpeed(3.5);
        $activity->setActivityDate(new \DateTimeImmutable());
        $activity->setSyncedAt(new \DateTimeImmutable());

        return $activity;
    }
}
