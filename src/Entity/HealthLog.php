<?php

namespace App\Entity;

use App\Repository\HealthLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HealthLogRepository::class)]
class HealthLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Assert\NotBlank(message: 'Timestamp must be provided or defaults to now.')]
    private \DateTimeImmutable $timestamp;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero(message: 'Systolic value must be positive.')]
    #[Assert\Range(min: 60, max: 250, notInRangeMessage: 'Systolic should be between 60 and 250.', groups: ['health_check'])]
    private ?int $systolic = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero(message: 'Diastolic value must be positive.')]
    #[Assert\Range(min: 40, max: 150, notInRangeMessage: 'Diastolic should be between 40 and 150.', groups: ['health_check'])]
    private ?int $diastolic = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero(message: 'Heart rate must be positive.')]
    #[Assert\Range(min: 30, max: 250, notInRangeMessage: 'Heart rate should be between 30 and 250.', groups: ['health_check'])]
    private ?int $heartRate = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\Positive(message: 'Weight must be positive.')]
    #[Assert\Range(min: 30, max: 400, notInRangeMessage: 'Weight should be between 30 and 400.', groups: ['health_check'])]
    private ?float $weight = null;

    #[ORM\Column(type: 'string', length: 10)]
    private string $emoji = '😐';

    public function __construct(?\DateTimeImmutable $timestamp = null)
    {
        $this->timestamp = $timestamp ?? new \DateTimeImmutable('UTC');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeImmutable $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getSystolic(): ?int
    {
        return $this->systolic;
    }

    public function setSystolic(?int $systolic): self
    {
        $this->systolic = $systolic;
        return $this;
    }

    public function getDiastolic(): ?int
    {
        return $this->diastolic;
    }

    public function setDiastolic(?int $diastolic): self
    {
        $this->diastolic = $diastolic;
        return $this;
    }

    public function getHeartRate(): ?int
    {
        return $this->heartRate;
    }

    public function setHeartRate(?int $heartRate): self
    {
        $this->heartRate = $heartRate;
        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): self
    {
        $this->weight = $weight;
        return $this;
    }

    public function getEmoji(): string
    {
        return $this->emoji;
    }

    public function setEmoji(string $emoji): self
    {
        // Allow any emoji but default to neutral if empty or invalid length
        $this->emoji = strlen($emoji) > 0 ? $emoji : '😐';
        return $this;
    }

    /**
     * @return bool Whether at least one measurement field is set
     */
    public function hasMeasurements(): bool
    {
        return null !== $this->systolic || null !== $this->diastolic || null !== $this->heartRate || null !== $this->weight;
    }
}
