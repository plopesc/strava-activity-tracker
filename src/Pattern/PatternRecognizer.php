<?php

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
            $speeds = array_filter($rawStreams['velocity_smooth']['data'], fn($v) => is_numeric($v) && $v > 0);
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

            if ($segA['type'] !== $segB['type']) {
                return false;
            }

            $distA = (float) $segA['distance_m'];
            $distB = (float) $segB['distance_m'];
            $maxDist = max($distA, $distB);

            if ($maxDist == 0.0) {
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
        $activity->setPatternSegments([[
            'type' => 'easy',
            'distance_m' => $easyDistance,
            'count' => 1,
            'avg_speed' => $activity->getAverageSpeed(),
            'avg_heartrate' => $activity->getAverageHeartrate(),
            'max_heartrate' => $activity->getMaxHeartrate(),
        ]]);
    }

    /**
     * Computes the coefficient of variation (stddev / mean) for an array of values.
     */
    private function computeCv(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        if ($mean == 0.0) {
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
     * @param array $laps
     * @return array|null
     */
    private function segmentByLaps(array $laps): ?array
    {
        $speeds = [];
        foreach ($laps as $lap) {
            $speeds[] = isset($lap['average_speed']) ? (float) $lap['average_speed'] : 0.0;
        }

        $medianSpeed = $this->median($speeds);

        if ($medianSpeed == 0.0) {
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
                    'distance_m' => $lapDistance,
                    'count' => 1,
                    'avg_speed' => $lapSpeed,
                    'avg_heartrate' => $lapHr,
                    'max_heartrate' => $lapMaxHr,
                ];
                continue;
            }

            $blockSpeed = $current['avg_speed'] ?? 0.0;
            $maxSpeed = max($lapSpeed, $blockSpeed);
            $similar = ($maxSpeed > 0.0 && (abs($lapSpeed - $blockSpeed) / $maxSpeed) <= $this->segmentTolerance);

            if ($similar) {
                $totalDist = $current['distance_m'] + $lapDistance;

                $current['avg_speed'] = $totalDist > 0
                    ? ($blockSpeed * $current['distance_m'] + $lapSpeed * $lapDistance) / $totalDist
                    : $lapSpeed;

                $currentHr = $current['avg_heartrate'];
                if ($currentHr === null && $lapHr === null) {
                    $current['avg_heartrate'] = null;
                } else {
                    $current['avg_heartrate'] = $totalDist > 0
                        ? (($currentHr ?? 0.0) * $current['distance_m'] + ($lapHr ?? 0.0) * $lapDistance) / $totalDist
                        : ($lapHr ?? 0.0);
                }

                if ($current['max_heartrate'] === null && $lapMaxHr === null) {
                    $current['max_heartrate'] = null;
                } else {
                    $current['max_heartrate'] = max($current['max_heartrate'] ?? 0.0, $lapMaxHr ?? 0.0);
                }

                $current['distance_m'] = $totalDist;
                $current['count']++;
            } else {
                $blocks[] = $current;
                $current = [
                    'distance_m' => $lapDistance,
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
            $blockSpeed = $block['avg_speed'] ?? 0.0;

            if ($blockSpeed > 1.15 * $medianSpeed) {
                $type = 'fast';
            } elseif ($blockSpeed < 0.85 * $medianSpeed) {
                $type = 'recovery';
            } else {
                $type = 'moderate';
            }

            $segments[] = array_merge(['type' => $type], $block);
        }

        return $this->applyWarmupCooldown($segments);
    }

    /**
     * Relabels the first and last segments if they are 'moderate' as warmup/cooldown.
     */
    private function applyWarmupCooldown(array $segments): array
    {
        if (count($segments) === 0) {
            return $segments;
        }

        // Relabel first segment if moderate
        if ($segments[0]['type'] === 'moderate' || $segments[0]['type'] === 'recovery') {
            $segments[0]['type'] = 'warmup';
        }

        // Relabel last segment if moderate (only if different from first)
        $last = count($segments) - 1;
        if ($last > 0 && ($segments[$last]['type'] === 'moderate' || $segments[$last]['type'] === 'recovery')) {
            $segments[$last]['type'] = 'cooldown';
        }

        return $segments;
    }

    /**
     * Builds the human-readable signature from training segments (fast + moderate only).
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
            $distStr = $this->formatDistance((float) $seg['distance_m']);
            $count = (int) $seg['count'];

            if ($count > 1) {
                $parts[] = sprintf('%d×%s', $count, $distStr);
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
            if ($km == floor($km)) {
                return (int) $km . 'km';
            }
            return rtrim(rtrim(number_format($km, 1), '0'), '.') . 'km';
        }

        return (int) $rounded . 'm';
    }

    /**
     * Computes the median of an array of numeric values.
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
     */
    private function extractTrainingSegments(array $segments): array
    {
        return array_values(array_filter($segments, fn($seg) => in_array($seg['type'], ['fast', 'moderate'], true)));
    }

    /**
     * Checks if two segments are consecutive (same type and distance within 10% tolerance).
     */
    private function areConsecutiveSegments(array $current, array $seg): bool
    {
        if ($seg['type'] !== $current['type']) {
            return false;
        }

        $currentDist = (float) $current['distance_m'];
        $segDist = (float) $seg['distance_m'];

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
     * Merges consecutive training segments of the same type and similar distance (rounded to 100m).
     */
    private function
    mergeTrainingSegmentsByTypeAndDistance(array $trainingSegments): array
    {
        if (count($trainingSegments) === 0) {
            return [];
        }

        $merged = [];
        $current = [
            'type' => null,
            'distance_m' => null,
        ];
        for ($i = 0; $i < count($trainingSegments); $i++) {
            $seg = $trainingSegments[$i];

            $consecutive = $this->areConsecutiveSegments($current, $seg);

            if ($consecutive) {
                $merged[array_key_last($merged)]['count']++;
            } else {
                $merged[] = [
                    'type' => $seg['type'],
                    'distance_m' => $seg['distance_m'],
                    'count' => 1,
                ];
                $current = [
                    'type' => $seg['type'],
                    'distance_m' => $seg['distance_m'],
                ];;
            }
        }

        return $merged;
    }
}
