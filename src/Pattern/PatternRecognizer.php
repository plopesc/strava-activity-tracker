<?php

declare(strict_types=1);

namespace App\Pattern;

use App\Entity\Activity;

class PatternRecognizer
{
    public function __construct(
        private float $paceCvThreshold = 0.18,
        private float $segmentTolerance = 0.12,
        private float $longRunThreshold = 12000,
    ) {}

    /**
     * Classifies an Activity and sets its patternType, patternSignature, and patternSegments.
     * Does NOT persist — caller is responsible for flushing.
     */
    public function classify(Activity $activity): void
    {
        $rawStreams = $activity->getRawStreams();

        // Step 1: Compute pace CV from velocity_smooth stream
        $speeds = [];
        if (isset($rawStreams['velocity_smooth']['data']) && is_array($rawStreams['velocity_smooth']['data'])) {
            $speeds = array_filter($rawStreams['velocity_smooth']['data'], static fn ($v) => is_numeric($v) && $v > 0);
            $speeds = array_values($speeds);
        }

        $cv = count($speeds) >= 2 ? $this->computeCv($speeds) : 1.0;

        // Step 2: Coarse classification
        if ($cv <= $this->paceCvThreshold) {
            $this->classifyAsEasyRun($activity);

            return;
        }

        // Step 3: Interval segmentation
        $segments = $this->trySegmentByLaps($activity->getRawLaps());

        if ($segments === null) {
            $activity->setPatternType(null);
            $activity->setPatternSignature(null);
            $activity->setPatternSegments(null);

            return;
        }

        $signature = $this->buildSignature($segments) ?: null;

        $activity->setPatternType($signature !== null ? 'interval' : null);
        $activity->setPatternSegments($signature !== null ? $segments : null);
        $activity->setPatternSignature($signature);
    }

    /**
     * Returns true if both activities share the same training pattern.
     */
    public function haveSamePattern(Activity $a, Activity $b): bool
    {
        $typeA = $a->getPatternType();
        $typeB = $b->getPatternType();

        if ($typeA === null || $typeB === null) {
            return false;
        }

        if ($typeA !== $typeB) {
            return false;
        }

        if ($typeA === 'short_run' || $typeA === 'long_run') {
            return true;
        }

        // interval: compare training segments only
        $trainingA = $this->extractTrainingSegments($a->getPatternSegments() ?? []);
        $trainingB = $this->extractTrainingSegments($b->getPatternSegments() ?? []);

        if (count($trainingA) !== count($trainingB)) {
            return false;
        }

        foreach ($trainingA as $i => $segA) {
            $segB = $trainingB[$i];

            if ($segA->type !== $segB->type) {
                return false;
            }

            $distA = $segA->distance;
            $distB = $segB->distance;
            $maxDist = max($distA, $distB);

            if ($maxDist === 0.0) {
                continue;
            }

            if (abs($distA - $distB) / $maxDist > $this->segmentTolerance) {
                return false;
            }
        }

        return true;
    }

    /**
     * Classifies an activity as an easy run (short or long).
     */
    private function classifyAsEasyRun(Activity $activity): void
    {
        $distance = $activity->getDistance() ?? 0.0;
        $easyDistance = floor($distance / 1000) * 1000;
        $easyKm = (int) ($easyDistance / 1000);

        $activity->setPatternType($distance > $this->longRunThreshold ? 'long_run' : 'short_run');
        $activity->setPatternSignature('easy ' . $easyKm . 'km');
        $activity->setPatternSegments([new Segment(
            type: SegmentType::Easy,
            distance: $easyDistance,
            count: 1,
            avgSpeed: $activity->getAverageSpeed(),
            avgHeartrate: $activity->getAverageHeartrate(),
            maxHeartrate: $activity->getMaxHeartrate(),
        )]);
    }

    /**
     * Computes the coefficient of variation (stddev / mean) for an array of values.
     *
     * @param list<float|int|numeric-string> $values
     */
    private function computeCv(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        if ($mean === 0.0) {
            return 0.0;
        }

        $sumSquaredDiff = 0.0;
        foreach ($values as $v) {
            $sumSquaredDiff += ($v - $mean) ** 2;
        }

        $stddev = sqrt($sumSquaredDiff / $count);

        return $stddev / $mean;
    }

    /**
     * Attempts lap-based segmentation. Returns null if not suitable.
     *
     * @param null|array<int|string, mixed> $rawLaps
     * @return null|Segment[]
     */
    private function trySegmentByLaps(?array $rawLaps): ?array
    {
        if ($rawLaps === null || count($rawLaps) < 3) {
            return null;
        }

        $lapDistances = array_column($rawLaps, 'distance');
        $lapDistances = array_filter($lapDistances, 'is_numeric');
        $lapDistances = array_values($lapDistances);

        if (count($lapDistances) < 3) {
            return null;
        }

        return $this->segmentByLaps($rawLaps);
    }

    /**
     * Performs lap-based segmentation.
     *
     * @param non-empty-array<int|string, mixed> $laps
     * @return null|Segment[]
     */
    private function segmentByLaps(array $laps): ?array
    {
        $speeds = [];
        foreach ($laps as $lap) {
            $speeds[] = isset($lap['average_speed']) ? (float) $lap['average_speed'] : 0.0;
        }

        $medianSpeed = $this->median($speeds);

        if ($medianSpeed === 0.0) {
            return null;
        }

        // Group consecutive laps into blocks based on speed similarity with the previous block.
        $blocks = [];
        $current = null;

        foreach ($laps as $lap) {
            $lapSpeed = isset($lap['average_speed']) ? (float) $lap['average_speed'] : 0.0;
            $lapDistance = isset($lap['distance']) ? (float) $lap['distance'] : 0.0;
            if ($lapDistance < 200) {
                continue;
            }
            $lapHr = isset($lap['average_heartrate']) ? (float) $lap['average_heartrate'] : null;
            $lapMaxHr = isset($lap['max_heartrate']) ? (float) $lap['max_heartrate'] : null;

            if ($current === null) {
                $current = [
                    'distance' => $lapDistance,
                    'count' => 1,
                    'avg_speed' => $lapSpeed,
                    'avg_heartrate' => $lapHr,
                    'max_heartrate' => $lapMaxHr,
                ];

                continue;
            }

            $blockSpeed = $current['avg_speed'];
            $maxSpeed = max($lapSpeed, $blockSpeed);
            $similar = ($maxSpeed > 0.0 && (abs($lapSpeed - $blockSpeed) / $maxSpeed) <= $this->segmentTolerance);

            if ($similar) {
                $totalDist = $current['distance'] + $lapDistance;

                $current['avg_speed'] = $totalDist > 0
                    ? ($blockSpeed * $current['distance'] + $lapSpeed * $lapDistance) / $totalDist
                    : $lapSpeed;

                $currentHr = $current['avg_heartrate'];
                if ($currentHr === null && $lapHr === null) {
                    $current['avg_heartrate'] = null;
                } else {
                    $current['avg_heartrate'] = $totalDist > 0
                        ? (($currentHr ?? 0.0) * $current['distance'] + ($lapHr ?? 0.0) * $lapDistance) / $totalDist
                        : ($lapHr ?? 0.0);
                }

                if ($current['max_heartrate'] === null && $lapMaxHr === null) {
                    $current['max_heartrate'] = null;
                } else {
                    $current['max_heartrate'] = max($current['max_heartrate'] ?? 0.0, $lapMaxHr ?? 0.0);
                }

                $current['distance'] = $totalDist;
                ++$current['count'];
            } else {
                $blocks[] = $current;
                $current = [
                    'distance' => $lapDistance,
                    'count' => 1,
                    'avg_speed' => $lapSpeed,
                    'avg_heartrate' => $lapHr,
                    'max_heartrate' => $lapMaxHr,
                ];
            }
        }

        if ($current !== null) {
            $blocks[] = $current;
        }

        // Classify each block by its average speed relative to the global median.
        $segments = [];
        foreach ($blocks as $block) {
            $blockSpeed = $block['avg_speed'];

            if ($blockSpeed > 1.15 * $medianSpeed) {
                $type = SegmentType::Fast;
            } elseif ($blockSpeed < 0.85 * $medianSpeed) {
                $type = SegmentType::Recovery;
            } else {
                $type = SegmentType::Moderate;
            }

            $segments[] = new Segment(
                type: $type,
                distance: $block['distance'],
                count: $block['count'],
                avgSpeed: $block['avg_speed'],
                avgHeartrate: $block['avg_heartrate'],
                maxHeartrate: $block['max_heartrate'],
            );
        }

        return $this->applyWarmupCooldown($segments);
    }

    /**
     * Relabels the first and last segments if they are 'moderate' as warmup/cooldown.
     *
     * @param Segment[] $segments
     * @return Segment[]
     */
    private function applyWarmupCooldown(array $segments): array
    {
        if (count($segments) === 0) {
            return $segments;
        }

        // Relabel first segment if moderate or recovery
        if ($segments[0]->type === SegmentType::Moderate || $segments[0]->type === SegmentType::Recovery) {
            $segments[0] = new Segment(
                type: SegmentType::Warmup,
                distance: $segments[0]->distance,
                count: $segments[0]->count,
                avgSpeed: $segments[0]->avgSpeed,
                avgHeartrate: $segments[0]->avgHeartrate,
                maxHeartrate: $segments[0]->maxHeartrate,
            );
        }

        // Relabel last segment if moderate or recovery (only if different from first)
        $last = count($segments) - 1;
        if ($last > 0 && ($segments[$last]->type === SegmentType::Moderate || $segments[$last]->type === SegmentType::Recovery)) {
            $segments[$last] = new Segment(
                type: SegmentType::Cooldown,
                distance: $segments[$last]->distance,
                count: $segments[$last]->count,
                avgSpeed: $segments[$last]->avgSpeed,
                avgHeartrate: $segments[$last]->avgHeartrate,
                maxHeartrate: $segments[$last]->maxHeartrate,
            );
        }

        return $segments;
    }

    /**
     * Builds the human-readable signature from training segments (fast + moderate only).
     *
     * @param Segment[] $segments
     */
    private function buildSignature(array $segments): string
    {
        $training = $this->extractTrainingSegments($segments);

        if (count($training) === 0) {
            return '';
        }

        $merged = $this->mergeTrainingSegmentsByTypeAndDistance($training);

        $parts = [];
        foreach ($merged as $seg) {
            $distStr = $this->formatDistance($seg->distance);

            if ($seg->count > 1) {
                $parts[] = sprintf('%d×%s', $seg->count, $distStr);
            } else {
                $parts[] = $distStr;
            }
        }

        return implode(' + ', $parts);
    }

    /**
     * Formats a distance in metres as a human-readable string rounded to nearest 100 m.
     */
    private function formatDistance(float $metres): string
    {
        $rounded = round($metres / 100) * 100;

        if ($rounded >= 1000) {
            $km = $rounded / 1000;
            // Format: remove trailing zero decimals but keep one decimal if needed
            if ($km === floor($km)) {
                return (int) $km . 'km';
            }

            return rtrim(rtrim(number_format($km, 1), '0'), '.') . 'km';
        }

        return (int) $rounded . 'm';
    }

    /**
     * Computes the median of an array of numeric values.
     *
     * @param list<float> $values
     */
    private function median(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $sorted = $values;
        sort($sorted);

        $mid = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($sorted[$mid - 1] + $sorted[$mid]) / 2.0;
        }

        return (float) $sorted[$mid];
    }

    /**
     * Extracts only training segments (fast and moderate) from a segments array.
     *
     * @param Segment[] $segments
     * @return Segment[]
     */
    private function extractTrainingSegments(array $segments): array
    {
        return array_values(array_filter($segments, static fn (Segment $seg) => $seg->type->isTraining()));
    }

    /**
     * Checks if two segments are consecutive (same type and distance within tolerance).
     */
    private function areConsecutiveSegments(Segment $current, Segment $seg): bool
    {
        if ($seg->type !== $current->type) {
            return false;
        }

        $currentDist = $current->distance;
        $segDist = $seg->distance;

        if ($currentDist === 0.0 && $segDist === 0.0) {
            return true;
        }

        $maxDist = max($currentDist, $segDist);
        if ($maxDist === 0.0) {
            return false;
        }

        return abs($currentDist - $segDist) / $maxDist <= $this->segmentTolerance;
    }

    /**
     * Merges consecutive training segments of the same type and similar distance.
     *
     * @param Segment[] $trainingSegments
     * @return Segment[]
     */
    private function mergeTrainingSegmentsByTypeAndDistance(array $trainingSegments): array
    {
        if (count($trainingSegments) === 0) {
            return [];
        }

        $merged = [];
        $current = new Segment(type: SegmentType::Easy, distance: 0.0, count: 0);

        for ($i = 0; $i < count($trainingSegments); ++$i) {
            $seg = $trainingSegments[$i];

            if ($this->areConsecutiveSegments($current, $seg)) {
                $last = $merged[array_key_last($merged)];
                $merged[array_key_last($merged)] = new Segment(
                    type: $last->type,
                    distance: $last->distance,
                    count: $last->count + 1,
                );
            } else {
                $merged[] = new Segment(
                    type: $seg->type,
                    distance: $seg->distance,
                    count: 1,
                );
                $current = $seg;
            }
        }

        return $merged;
    }
}
