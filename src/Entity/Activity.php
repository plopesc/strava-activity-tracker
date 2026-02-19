<?php

namespace App\Entity;

use App\Repository\ActivityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'activity')]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'bigint', unique: true)]
    private ?string $stravaId = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $activityDate = null;

    #[ORM\Column(type: 'float')]
    private ?float $distance = null;

    #[ORM\Column(type: 'integer')]
    private ?int $elapsedTime = null;

    #[ORM\Column(type: 'float')]
    private ?float $averageSpeed = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $averageHeartrate = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $rawLaps = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $rawStreams = null;

    #[ORM\ManyToOne(targetEntity: Gear::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Gear $gear = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $sportType = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $maxHeartrate = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $patternType = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $patternSignature = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $patternSegments = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $syncedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStravaId(): ?string
    {
        return $this->stravaId;
    }

    public function setStravaId(string $stravaId): static
    {
        $this->stravaId = $stravaId;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getActivityDate(): ?\DateTimeImmutable
    {
        return $this->activityDate;
    }

    public function setActivityDate(\DateTimeImmutable $activityDate): static
    {
        $this->activityDate = $activityDate;

        return $this;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(float $distance): static
    {
        $this->distance = $distance;

        return $this;
    }

    public function getElapsedTime(): ?int
    {
        return $this->elapsedTime;
    }

    public function setElapsedTime(int $elapsedTime): static
    {
        $this->elapsedTime = $elapsedTime;

        return $this;
    }

    public function getAverageSpeed(): ?float
    {
        return $this->averageSpeed;
    }

    public function setAverageSpeed(float $averageSpeed): static
    {
        $this->averageSpeed = $averageSpeed;

        return $this;
    }

    public function getAverageHeartrate(): ?float
    {
        return $this->averageHeartrate;
    }

    public function setAverageHeartrate(?float $averageHeartrate): static
    {
        $this->averageHeartrate = $averageHeartrate;

        return $this;
    }

    public function getRawLaps(): ?array
    {
        return $this->rawLaps;
    }

    public function setRawLaps(?array $rawLaps): static
    {
        $this->rawLaps = $rawLaps;

        return $this;
    }

    public function getRawStreams(): ?array
    {
        return $this->rawStreams;
    }

    public function setRawStreams(?array $rawStreams): static
    {
        $this->rawStreams = $rawStreams;

        return $this;
    }

    public function getGear(): ?Gear
    {
        return $this->gear;
    }

    public function setGear(?Gear $gear): static
    {
        $this->gear = $gear;

        return $this;
    }

    public function getSportType(): ?string
    {
        return $this->sportType;
    }

    public function setSportType(?string $sportType): static
    {
        $this->sportType = $sportType;

        return $this;
    }

    public function getMaxHeartrate(): ?float
    {
        return $this->maxHeartrate;
    }

    public function setMaxHeartrate(?float $maxHeartrate): static
    {
        $this->maxHeartrate = $maxHeartrate;

        return $this;
    }

    public function getPatternType(): ?string
    {
        return $this->patternType;
    }

    public function setPatternType(?string $patternType): static
    {
        $this->patternType = $patternType;

        return $this;
    }

    public function getPatternSignature(): ?string
    {
        return $this->patternSignature;
    }

    public function setPatternSignature(?string $patternSignature): static
    {
        $this->patternSignature = $patternSignature;

        return $this;
    }

    public function getPatternSegments(): ?array
    {
        return $this->patternSegments;
    }

    public function setPatternSegments(?array $patternSegments): static
    {
        $this->patternSegments = $patternSegments;

        return $this;
    }

    public function getSyncedAt(): ?\DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(\DateTimeImmutable $syncedAt): static
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }
}
