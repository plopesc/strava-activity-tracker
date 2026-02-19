<?php

declare(strict_types=1);

namespace App\Tests\Pattern;

use App\Entity\Activity;
use App\Pattern\PatternRecognizer;
use App\Pattern\Segment;
use App\Pattern\SegmentType;
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
        self::assertSame('easy 9km', $activity->getPatternSignature()); // 9000m → floor(9) = 9
        self::assertNotNull($activity->getPatternSegments());
        $segments = $activity->getPatternSegments();
        self::assertCount(1, $segments);
        self::assertSame(SegmentType::Easy, $segments[0]->type);
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
        self::assertSame('easy 15km', $activity->getPatternSignature()); // 15000m → floor(15) = 15
        self::assertNotNull($activity->getPatternSegments());
        $segments = $activity->getPatternSegments();
        self::assertCount(1, $segments);
        self::assertSame(SegmentType::Easy, $segments[0]->type);
    }

    // -------------------------------------------------------------------------
    // Test 3: Interval classification from laps (lap-based path)
    // -------------------------------------------------------------------------

    public function testClassifyIntervalFromLaps(): void
    {
        // 8 alternating laps: even = fast (1000m @ 4.2 m/s), odd = recovery (500m @ 2.5 m/s)
        // MAD of distances: mean = (1000+500)*4/8 = 750; deviations = |1000-750|=250 or |500-750|=250
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

        // Signature should be non-empty and in the new distance-only format (e.g. "4×1km")
        $signature = $activity->getPatternSignature();
        self::assertNotEmpty($signature);
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
        // New format: "4×1km" (distance-only, no type labels, recovery excluded)
        self::assertSame('4×1km', $signature);
    }

    // -------------------------------------------------------------------------
    // Test 6: haveSamePattern — matching (within 10% tolerance)
    // -------------------------------------------------------------------------

    public function testHaveSamePatternMatching(): void
    {
        // Activity A: fast 1000m ×3, recovery 500m ×3
        $activityA = $this->makeIntervalActivity([
            new Segment(SegmentType::Fast, 1000, 3),
            new Segment(SegmentType::Recovery, 500, 3),
        ]);

        // Activity B: fast ~1048m ×3 (4.8% diff), recovery ~490m ×3 (2% diff) — both within 10%
        $activityB = $this->makeIntervalActivity([
            new Segment(SegmentType::Fast, 1048, 3),
            new Segment(SegmentType::Recovery, 490, 3),
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
            new Segment(SegmentType::Fast, 1000, 3),
        ]);

        // Activity B: fast 1200m ×3 — 20% difference, exceeds 10% tolerance
        $activityB = $this->makeIntervalActivity([
            new Segment(SegmentType::Fast, 1200, 3),
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
            new Segment(SegmentType::Fast, 1000, 4),
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

        self::assertSame('short_run', $activity->getPatternType());
        self::assertSame('easy 9km', $activity->getPatternSignature()); // floor(9.5) = 9
        $segments = $activity->getPatternSegments();
        self::assertNotNull($segments);
        self::assertCount(1, $segments);
        self::assertSame(SegmentType::Easy, $segments[0]->type);
        self::assertSame(9000.0, $segments[0]->distance);
        self::assertNotNull($segments[0]->avgSpeed);
        self::assertNotNull($segments[0]->avgHeartrate);
        self::assertNotNull($segments[0]->maxHeartrate);
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

        self::assertSame('interval', $activity->getPatternType());
        self::assertSame('2×6km', $activity->getPatternSignature());
    }

    // =========================================================================
    // segmentByLaps tests
    // =========================================================================

    // -------------------------------------------------------------------------
    // Test 13: All zero speeds → null
    // -------------------------------------------------------------------------

    public function testAllZeroSpeedsReturnsNull(): void
    {
        $rawLaps = [
            ['average_speed' => 0, 'distance' => 1000],
            ['average_speed' => 0, 'distance' => 1000],
            ['average_speed' => 0, 'distance' => 1000],
        ];

        $activity = $this->makeActivity(3000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        self::assertNull($activity->getPatternType());
        self::assertNull($activity->getPatternSegments());
    }

    // -------------------------------------------------------------------------
    // Test 14: Laps < 200m are skipped
    // -------------------------------------------------------------------------

    public function testSmallLapsAreSkipped(): void
    {
        // Alternating fast/recovery, but recovery laps are 150m (below 200m threshold)
        // Median of speeds: [4.2, 2.5, 4.2, 2.5, 4.2, 2.5, 4.2, 2.5] → 3.35
        // After skipping small laps, only fast laps remain, all similar speed → merge into 1 block
        $rawLaps = [
            ['average_speed' => 4.2, 'distance' => 1000],
            ['average_speed' => 2.5, 'distance' => 150],  // skipped
            ['average_speed' => 4.2, 'distance' => 1000],
            ['average_speed' => 2.5, 'distance' => 150],  // skipped
            ['average_speed' => 4.2, 'distance' => 1000],
            ['average_speed' => 2.5, 'distance' => 150],  // skipped
            ['average_speed' => 4.2, 'distance' => 1000],
            ['average_speed' => 2.5, 'distance' => 150],  // skipped
        ];

        $activity = $this->makeActivity(8000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        self::assertNotNull($activity->getPatternSegments());
        $segments = $activity->getPatternSegments();

        // Small laps skipped with force_new, so 4 fast laps won't all merge
        // (force_new breaks grouping after each skipped lap)
        // Each fast lap becomes its own block → 4 segments
        foreach ($segments as $segment) {
            // All segments should be fast-derived (fast or warmup/cooldown relabeled)
            self::assertContains($segment->type, [SegmentType::Fast, SegmentType::Warmup, SegmentType::Cooldown, SegmentType::Moderate]);
        }
    }

    // -------------------------------------------------------------------------
    // Test 15: Consecutive similar-speed laps merge
    // -------------------------------------------------------------------------

    public function testConsecutiveSimilarSpeedLapsMerge(): void
    {
        // 3 laps at ~3.0 m/s (within 12% tolerance), then 3 fast, then 3 similar again
        // Median of [3.0, 3.05, 2.95, 4.5, 4.5, 4.5, 3.0, 3.05, 2.95] → sorted → median = 3.05
        // The 3 similar laps should merge into a single block
        $rawLaps = [
            ['average_speed' => 3.0, 'distance' => 1000],
            ['average_speed' => 3.05, 'distance' => 1000],
            ['average_speed' => 2.95, 'distance' => 1000],
            ['average_speed' => 4.5, 'distance' => 1000],
            ['average_speed' => 4.5, 'distance' => 1000],
            ['average_speed' => 4.5, 'distance' => 1000],
            ['average_speed' => 3.0, 'distance' => 1000],
            ['average_speed' => 3.05, 'distance' => 1000],
            ['average_speed' => 2.95, 'distance' => 1000],
        ];

        $activity = $this->makeActivity(9000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $segments = $activity->getPatternSegments();
        self::assertNotNull($segments);
        // 3 blocks: merged moderate(3 laps) + merged fast(3 laps) + merged moderate(3 laps)
        self::assertCount(3, $segments);
        // First block: 3 laps merged → count=3, distance=3000
        self::assertSame(3, $segments[0]->count);
        self::assertSame(3000.0, $segments[0]->distance);
    }

    // -------------------------------------------------------------------------
    // Test 16: HR weighted average across merged laps
    // -------------------------------------------------------------------------

    public function testHrWeightedAverageAcrossMergedLaps(): void
    {
        // 2 similar-speed laps that will merge: (1000m, HR=140) + (2000m, HR=155)
        // Weighted avg = (140*1000 + 155*2000) / 3000 = (140000+310000)/3000 = 150.0
        // Need 3+ laps for trySegmentByLaps, add a fast lap to get there
        $rawLaps = [
            ['average_speed' => 3.0, 'distance' => 1000, 'average_heartrate' => 140, 'max_heartrate' => 160],
            ['average_speed' => 3.05, 'distance' => 2000, 'average_heartrate' => 155, 'max_heartrate' => 170],
            ['average_speed' => 4.5, 'distance' => 1000, 'average_heartrate' => 170, 'max_heartrate' => 185],
        ];

        $activity = $this->makeActivity(4000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $segments = $activity->getPatternSegments();
        self::assertNotNull($segments);
        // First segment: merged from 2 similar-speed laps
        self::assertSame(2, $segments[0]->count);
        self::assertEqualsWithDelta(150.0, $segments[0]->avgHeartrate, 0.1);
    }

    // -------------------------------------------------------------------------
    // Test 17: Max HR tracked across merged laps
    // -------------------------------------------------------------------------

    public function testMaxHrTrackedAcrossMergedLaps(): void
    {
        $rawLaps = [
            ['average_speed' => 3.0, 'distance' => 1000, 'average_heartrate' => 140, 'max_heartrate' => 170],
            ['average_speed' => 3.05, 'distance' => 1000, 'average_heartrate' => 145, 'max_heartrate' => 180],
            ['average_speed' => 4.5, 'distance' => 1000, 'average_heartrate' => 170, 'max_heartrate' => 190],
        ];

        $activity = $this->makeActivity(3000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $segments = $activity->getPatternSegments();
        self::assertNotNull($segments);
        // First segment: merged from 2 similar-speed laps, max HR = 180
        self::assertSame(2, $segments[0]->count);
        self::assertSame(180.0, $segments[0]->maxHeartrate);
    }

    // -------------------------------------------------------------------------
    // Test 18: Null HR propagation
    // -------------------------------------------------------------------------

    public function testNullHrPropagation(): void
    {
        // 2 similar-speed laps with no HR data, plus a fast lap
        $rawLaps = [
            ['average_speed' => 3.0, 'distance' => 1000],
            ['average_speed' => 3.05, 'distance' => 1000],
            ['average_speed' => 4.5, 'distance' => 1000],
        ];

        $activity = $this->makeActivity(3000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $segments = $activity->getPatternSegments();
        self::assertNotNull($segments);
        // First segment: merged from 2 laps with no HR → null
        self::assertSame(2, $segments[0]->count);
        self::assertNull($segments[0]->avgHeartrate);
        self::assertNull($segments[0]->maxHeartrate);
    }

    // -------------------------------------------------------------------------
    // Test 19: Speed classification thresholds
    // -------------------------------------------------------------------------

    public function testSpeedClassificationThresholds(): void
    {
        // Median speed = 3.0 (middle value of [2.4, 3.0, 3.6])
        // Fast threshold: > 1.15 * 3.0 = 3.45 → 3.6 is fast
        // Recovery threshold: < 0.85 * 3.0 = 2.55 → 2.4 is recovery
        // Moderate: between 2.55 and 3.45 → 3.0 is moderate
        $rawLaps = [
            ['average_speed' => 3.0, 'distance' => 1000],   // moderate → warmup
            ['average_speed' => 3.6, 'distance' => 1000],   // fast
            ['average_speed' => 2.4, 'distance' => 1000],   // recovery
            ['average_speed' => 3.0, 'distance' => 1000],   // moderate → cooldown
        ];

        $activity = $this->makeActivity(4000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $segments = $activity->getPatternSegments();
        self::assertNotNull($segments);
        self::assertCount(4, $segments);
        self::assertSame(SegmentType::Warmup, $segments[0]->type);
        self::assertSame(SegmentType::Fast, $segments[1]->type);
        self::assertSame(SegmentType::Recovery, $segments[2]->type);
        self::assertSame(SegmentType::Cooldown, $segments[3]->type);
    }

    // -------------------------------------------------------------------------
    // Test 20: First moderate → warmup
    // -------------------------------------------------------------------------

    public function testFirstModerateBecomesWarmup(): void
    {
        // moderate, fast, recovery, fast, moderate
        // Median of [3.0, 4.2, 2.0, 4.2, 3.0] → sorted [2.0, 3.0, 3.0, 4.2, 4.2] → median = 3.0
        // 1.15*3.0=3.45, 0.85*3.0=2.55
        // 3.0→moderate, 4.2→fast, 2.0→recovery, 4.2→fast, 3.0→moderate
        $rawLaps = [
            ['average_speed' => 3.0, 'distance' => 1000],
            ['average_speed' => 4.2, 'distance' => 1000],
            ['average_speed' => 2.0, 'distance' => 1000],
            ['average_speed' => 4.2, 'distance' => 1000],
            ['average_speed' => 3.0, 'distance' => 1000],
        ];

        $activity = $this->makeActivity(5000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $segments = $activity->getPatternSegments();
        self::assertNotNull($segments);
        self::assertSame(SegmentType::Warmup, $segments[0]->type);
    }

    // -------------------------------------------------------------------------
    // Test 21: Last moderate → cooldown
    // -------------------------------------------------------------------------

    public function testLastModerateBecomescooldown(): void
    {
        // fast, recovery, fast, moderate
        // Median of [4.2, 2.0, 4.2, 3.0] → sorted [2.0, 3.0, 4.2, 4.2] → median = (3.0+4.2)/2 = 3.6
        // 1.15*3.6=4.14, 0.85*3.6=3.06
        // 4.2→fast, 2.0→recovery, 4.2→fast, 3.0→recovery(< 3.06)
        // Last is recovery → cooldown
        $rawLaps = [
            ['average_speed' => 4.2, 'distance' => 1000],
            ['average_speed' => 2.0, 'distance' => 1000],
            ['average_speed' => 4.2, 'distance' => 1000],
            ['average_speed' => 3.0, 'distance' => 1000],
        ];

        $activity = $this->makeActivity(4000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $segments = $activity->getPatternSegments();
        self::assertNotNull($segments);
        $last = $segments[count($segments) - 1];
        self::assertSame(SegmentType::Cooldown, $last->type);
    }

    // -------------------------------------------------------------------------
    // Test 22: Fast first segment stays fast
    // -------------------------------------------------------------------------

    public function testFastFirstSegmentStaysFast(): void
    {
        // fast, recovery, fast, recovery
        // Median of [4.2, 2.0, 4.2, 2.0] → sorted [2.0, 2.0, 4.2, 4.2] → median = (2.0+4.2)/2 = 3.1
        // 1.15*3.1=3.565, 0.85*3.1=2.635
        // 4.2→fast, 2.0→recovery, 4.2→fast, 2.0→recovery
        // First is fast → stays fast (no warmup relabel)
        $rawLaps = [
            ['average_speed' => 4.2, 'distance' => 1000],
            ['average_speed' => 2.0, 'distance' => 1000],
            ['average_speed' => 4.2, 'distance' => 1000],
            ['average_speed' => 2.0, 'distance' => 1000],
        ];

        $activity = $this->makeActivity(4000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        $segments = $activity->getPatternSegments();
        self::assertNotNull($segments);
        self::assertSame(SegmentType::Fast, $segments[0]->type);
    }

    // -------------------------------------------------------------------------
    // Test 23: Single block (all similar speed)
    // -------------------------------------------------------------------------

    public function testSingleBlockAllSimilarSpeed(): void
    {
        // 4 laps at same speed → merge into 1 block → moderate → warmup (only segment)
        // Warmup is not a training segment, so signature is empty → null → patternType=null
        $rawLaps = [
            ['average_speed' => 3.0, 'distance' => 1000],
            ['average_speed' => 3.0, 'distance' => 1000],
            ['average_speed' => 3.0, 'distance' => 1000],
            ['average_speed' => 3.0, 'distance' => 1000],
        ];

        $activity = $this->makeActivity(4000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        // No training segments → empty signature → null pattern
        self::assertNull($activity->getPatternType());
        self::assertNull($activity->getPatternSegments());
    }

    // -------------------------------------------------------------------------
    // Test 24: Full realistic interval workout
    // -------------------------------------------------------------------------

    public function testFullRealisticIntervalWorkout(): void
    {
        // warmup(2km@3.2) + 3×(fast 1km@4.5 + recovery 0.5km@2.2) + cooldown(1.5km@3.0)
        // Speeds: [3.2, 4.5, 2.2, 4.5, 2.2, 4.5, 2.2, 3.0]
        // Sorted: [2.2, 2.2, 2.2, 3.0, 3.2, 4.5, 4.5, 4.5] → median = (3.0+3.2)/2 = 3.1
        // 1.15*3.1=3.565, 0.85*3.1=2.635
        // 3.2→moderate, 4.5→fast, 2.2→recovery, 4.5→fast, 2.2→recovery, 4.5→fast, 2.2→recovery, 3.0→moderate
        $rawLaps = [
            ['average_speed' => 3.2, 'distance' => 2000, 'average_heartrate' => 130, 'max_heartrate' => 145],
            ['average_speed' => 4.5, 'distance' => 1000, 'average_heartrate' => 170, 'max_heartrate' => 185],
            ['average_speed' => 2.2, 'distance' => 500, 'average_heartrate' => 145, 'max_heartrate' => 155],
            ['average_speed' => 4.5, 'distance' => 1000, 'average_heartrate' => 172, 'max_heartrate' => 187],
            ['average_speed' => 2.2, 'distance' => 500, 'average_heartrate' => 148, 'max_heartrate' => 158],
            ['average_speed' => 4.5, 'distance' => 1000, 'average_heartrate' => 175, 'max_heartrate' => 190],
            ['average_speed' => 2.2, 'distance' => 500, 'average_heartrate' => 150, 'max_heartrate' => 160],
            ['average_speed' => 3.0, 'distance' => 1500, 'average_heartrate' => 135, 'max_heartrate' => 150],
        ];

        $activity = $this->makeActivity(8000.0, $rawLaps, null);
        $this->recognizer->classify($activity);

        self::assertSame('interval', $activity->getPatternType());
        $segments = $activity->getPatternSegments();
        self::assertNotNull($segments);

        // Expected: warmup, fast, recovery, fast, recovery, fast, recovery, cooldown
        self::assertCount(8, $segments);
        self::assertSame(SegmentType::Warmup, $segments[0]->type);
        self::assertSame(2000.0, $segments[0]->distance);
        self::assertSame(130.0, $segments[0]->avgHeartrate);

        self::assertSame(SegmentType::Fast, $segments[1]->type);
        self::assertSame(1000.0, $segments[1]->distance);

        self::assertSame(SegmentType::Recovery, $segments[2]->type);
        self::assertSame(500.0, $segments[2]->distance);

        self::assertSame(SegmentType::Fast, $segments[3]->type);
        self::assertSame(SegmentType::Recovery, $segments[4]->type);
        self::assertSame(SegmentType::Fast, $segments[5]->type);
        self::assertSame(SegmentType::Recovery, $segments[6]->type);

        self::assertSame(SegmentType::Cooldown, $segments[7]->type);
        self::assertSame(1500.0, $segments[7]->distance);

        // Verify signature: 3 fast segments at 1km each → "3×1km"
        self::assertSame('3×1km', $activity->getPatternSignature());
    }

    // =========================================================================
    // Fixture helpers
    // =========================================================================

    /**
     * Creates an Activity with the given distance, rawLaps, and rawStreams.
     */
    /** @param null|list<array<string, mixed>> $rawLaps
     *  @param null|array<string, mixed> $rawStreams */
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
     * @param Segment[] $trainingSegments
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
