<?php

declare(strict_types=1);

namespace App\Pattern;

readonly class Segment
{
    public function __construct(
        public SegmentType $type,
        public float $distance,
        public int $count,
        public ?float $avgSpeed = null,
        public ?float $avgHeartrate = null,
        public ?float $maxHeartrate = null,
    ) {}

    /**
     * Serializes to the array format used in Doctrine JSON storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'distance_m' => $this->distance,
            'count' => $this->count,
            'avg_speed' => $this->avgSpeed,
            'avg_heartrate' => $this->avgHeartrate,
            'max_heartrate' => $this->maxHeartrate,
        ];
    }

    /**
     * Creates a Segment from the array format stored in Doctrine JSON.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: SegmentType::from((string) $data['type']),
            distance: (float) $data['distance_m'],
            count: (int) ($data['count'] ?? 1),
            avgSpeed: isset($data['avg_speed']) ? (float) $data['avg_speed'] : null,
            avgHeartrate: isset($data['avg_heartrate']) ? (float) $data['avg_heartrate'] : null,
            maxHeartrate: isset($data['max_heartrate']) ? (float) $data['max_heartrate'] : null,
        );
    }
}
