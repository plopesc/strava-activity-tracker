<?php

declare(strict_types=1);

namespace App\Pattern;

use App\Entity\Activity;

class PatternRecognizer
{
    public function __construct(
        private float $paceCvThreshold = 0.10,
        private float $lapMadThreshold = 200.0,
        private float $segmentTolerance = 0.10,
    ) {}

    /**
     * Classifies an Activity and sets its patternType, patternSignature, and patternSegments.
     * Does NOT persist — caller is responsible for flushing.
     */
    public function classify(Activity $activity): void
    {
        $distance = $activity->getDistance() ?? 0.0;
        $rawStreams = $activity->getRawStreams();

        // Step 1: Compute pace CV from velocity_smooth stream
        $speeds = [];
        if (isset($rawStreams['velocity_smooth']['data']) && is_array($rawStreams['velocity_smooth']['data'])) {
            $filtered = array_filter($rawStreams['velocity_smooth']['data'], static fn ($v) => is_numeric($v) && $v > 0);
            $speeds = array_values(array_map(static fn ($v): float => (float) $v, $filtered));
        }

        $cv = count($speeds) >= 2 ? $this->computeCv($speeds) : 1.0;

        // Step 2: Coarse classification
        if ($distance >= 8000 && $distance <= 12000 && $cv <= $this->paceCvThreshold) {
            $activity->setPatternType('short_run');
            $activity->setPatternSignature('short_run');
            $activity->setPatternSegments(null);

            return;
        }

        if ($distance > 12000 && $cv <= $this->paceCvThreshold) {
            $activity->setPatternType('long_run');
            $activity->setPatternSignature('long_run');
            $activity->setPatternSegments(null);

            return;
        }

        // Step 3: Interval segmentation
        $segments = $this->trySegmentByLaps($activity->getRawLaps());

        if ($segments === null) {
            $segments = $this->trySegmentByStream($speeds);
        }

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
     * Computes the coefficient of variation (stddev / mean) for an array of values.
     *
     * @param array<int, float> $values
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
     * Computes the mean absolute deviation of an array of values.
     *
     * @param list<float|int|string> $values
     */
    private function medianAbsoluteDeviation(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $floatValues = array_map(static fn ($v): float => (float) $v, $values);
        $mean = array_sum($floatValues) / $count;

        $absDeviations = array_map(static fn ($v) => abs($v - $mean), $floatValues);

        return array_sum($absDeviations) / $count;
    }

    /**
     * Attempts lap-based segmentation. Returns null if not suitable.
     *
     * @param array<int|string, mixed>|null $rawLaps
     * @return array<int, array<string, mixed>>|null
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

        $mad = $this->medianAbsoluteDeviation($lapDistances);
        if ($mad <= $this->lapMadThreshold) {
            return null;
        }

        return $this->segmentByLaps($rawLaps);
    }

    /**
     * Performs lap-based segmentation.
     *
     * @param array<int|string, mixed> $laps
     * @return array<int, array<string, mixed>>|null
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

        $labeled = [];
        foreach ($laps as $lap) {
            $speed = isset($lap['average_speed']) ? (float) $lap['average_speed'] : 0.0;
            $distance = isset($lap['distance']) ? (float) $lap['distance'] : 0.0;

            if ($speed > 1.15 * $medianSpeed) {
                $type = 'fast';
            } elseif ($speed < 0.85 * $medianSpeed) {
                $type = 'recovery';
            } else {
                $type = 'moderate';
            }

            $labeled[] = ['type' => $type, 'distance_m' => $distance, 'count' => 1];
        }

        $merged = $this->mergeSameType($labeled);

        return $this->applyWarmupCooldown($merged);
    }

    /**
     * Attempts stream-based segmentation. Returns null if no valid stream data.
     *
     * @param array<int, float> $speeds
     * @return array<int, array<string, mixed>>|null
     */
    private function trySegmentByStream(array $speeds): ?array
    {
        if (count($speeds) < 30) {
            return null;
        }

        return $this->segmentByStream($speeds);
    }

    /**
     * Performs stream-based segmentation on per-second speed data.
     *
     * @param array<int, float> $streamData Array of per-second speed values (m/s)
     * @return array<int, array<string, mixed>>|null
     */
    private function segmentByStream(array $streamData): ?array
    {
        $smoothed = $this->movingAverage($streamData, 30);

        if (count($smoothed) === 0) {
            return null;
        }

        $globalMedian = $this->median($smoothed);

        if ($globalMedian === 0.0) {
            return null;
        }

        // Classify each sample
        $classified = [];
        foreach ($smoothed as $speed) {
            if ($speed > 1.15 * $globalMedian) {
                $classified[] = ['type' => 'fast', 'speed' => $speed];
            } elseif ($speed < 0.85 * $globalMedian) {
                $classified[] = ['type' => 'recovery', 'speed' => $speed];
            } else {
                $classified[] = ['type' => 'moderate', 'speed' => $speed];
            }
        }

        // Group consecutive same-type samples
        $groups = [];
        $currentType = $classified[0]['type'];
        $currentSpeeds = [$classified[0]['speed']];

        for ($i = 1; $i < count($classified); ++$i) {
            if ($classified[$i]['type'] === $currentType) {
                $currentSpeeds[] = $classified[$i]['speed'];
            } else {
                $groups[] = ['type' => $currentType, 'speeds' => $currentSpeeds];
                $currentType = $classified[$i]['type'];
                $currentSpeeds = [$classified[$i]['speed']];
            }
        }
        $groups[] = ['type' => $currentType, 'speeds' => $currentSpeeds];

        // Convert count of seconds to distance_m, filter < 200 m
        $segments = [];
        foreach ($groups as $group) {
            $countSeconds = count($group['speeds']);
            $avgSpeed = array_sum($group['speeds']) / $countSeconds;
            $distanceM = $avgSpeed * $countSeconds;

            if ($distanceM < 200.0) {
                continue;
            }

            $segments[] = [
                'type' => $group['type'],
                'distance_m' => $distanceM,
                'count' => 1,
            ];
        }

        if (count($segments) === 0) {
            return null;
        }

        // Merge adjacent same-type segments after filtering
        $segments = $this->mergeSameType($segments);

        return $this->applyWarmupCooldown($segments);
    }

    /**
     * Merges consecutive segments of the same type, summing distance_m and incrementing count.
     *
     * @param array<int, array<string, mixed>> $segments
     * @return array<int, array<string, mixed>>
     */
    private function mergeSameType(array $segments): array
    {
        if (count($segments) === 0) {
            return [];
        }

        $merged = [];
        $current = $segments[0];

        for ($i = 1; $i < count($segments); ++$i) {
            $seg = $segments[$i];
            if ($seg['type'] === $current['type']) {
                $current['distance_m'] += $seg['distance_m'];
                $current['count'] += $seg['count'];
            } else {
                $merged[] = $current;
                $current = $seg;
            }
        }
        $merged[] = $current;

        return $merged;
    }

    /**
     * Relabels the first and last segments if they are 'moderate' as warmup/cooldown.
     *
     * @param array<int, array<string, mixed>> $segments
     * @return array<int, array<string, mixed>>
     */
    private function applyWarmupCooldown(array $segments): array
    {
        if (count($segments) === 0) {
            return $segments;
        }

        // Relabel first segment if moderate
        if ($segments[0]['type'] === 'moderate') {
            $segments[0]['type'] = 'warmup';
        }

        // Relabel last segment if moderate (only if different from first)
        $last = count($segments) - 1;
        if ($last > 0 && $segments[$last]['type'] === 'moderate') {
            $segments[$last]['type'] = 'cooldown';
        }

        return $segments;
    }

    /**
     * Builds the human-readable signature from training segments (fast + recovery only).
     *
     * @param array<int, array<string, mixed>> $segments
     */
    private function buildSignature(array $segments): string
    {
        $training = $this->extractTrainingSegments($segments);

        if (count($training) === 0) {
            return '';
        }

        $parts = [];
        foreach ($training as $seg) {
            $distStr = $this->formatDistance((float) $seg['distance_m']);
            $count = (int) $seg['count'];
            $type = $seg['type'];

            if ($count > 1) {
                $parts[] = sprintf('%d×%s %s', $count, $distStr, $type);
            } else {
                $parts[] = sprintf('%s %s', $distStr, $type);
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
     * @param array<int, float> $values
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
     * Applies a moving average with the given window size to smooth data.
     *
     * @param array<int, float> $data
     * @return array<int, float>
     */
    private function movingAverage(array $data, int $window): array
    {
        $count = count($data);
        if ($count === 0 || $window <= 0) {
            return $data;
        }

        $result = [];
        for ($i = 0; $i < $count; ++$i) {
            $start = max(0, $i - (int) floor($window / 2));
            $end = min($count - 1, $start + $window - 1);
            // Adjust start if end is clamped
            $start = max(0, $end - $window + 1);

            $slice = array_slice($data, $start, $end - $start + 1);
            $result[] = array_sum($slice) / count($slice);
        }

        return $result;
    }

    /**
     * Extracts only training segments (fast and recovery) from a segments array.
     *
     * @param array<int, array<string, mixed>> $segments
     * @return array<int, array<string, mixed>>
     */
    private function extractTrainingSegments(array $segments): array
    {
        return array_values(array_filter($segments, static fn ($seg) => in_array($seg['type'], ['fast', 'recovery'], true)));
    }
}
