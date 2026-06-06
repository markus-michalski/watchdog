<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CheckStatus;
use App\Repository\AlertStateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertStateRepository::class)]
#[ORM\Table(name: 'alert_states')]
class AlertState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'alertState', targetEntity: SiteCheck::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?SiteCheck $check = null;

    #[ORM\Column(enumType: CheckStatus::class)]
    private CheckStatus $currentStatus = CheckStatus::Unknown;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastAlertSentAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $lastStatusChange;

    /** Consecutive failures since last status change */
    #[ORM\Column]
    private int $failCount = 0;

    public function __construct()
    {
        $this->lastStatusChange = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCheck(): ?SiteCheck
    {
        return $this->check;
    }

    public function setCheck(?SiteCheck $check): static
    {
        $this->check = $check;

        return $this;
    }

    public function getCurrentStatus(): CheckStatus
    {
        return $this->currentStatus;
    }

    public function setCurrentStatus(CheckStatus $currentStatus): static
    {
        $this->currentStatus = $currentStatus;

        return $this;
    }

    public function getLastAlertSentAt(): ?\DateTimeImmutable
    {
        return $this->lastAlertSentAt;
    }

    public function setLastAlertSentAt(?\DateTimeImmutable $lastAlertSentAt): static
    {
        $this->lastAlertSentAt = $lastAlertSentAt;

        return $this;
    }

    public function getLastStatusChange(): \DateTimeImmutable
    {
        return $this->lastStatusChange;
    }

    public function setLastStatusChange(\DateTimeImmutable $lastStatusChange): static
    {
        $this->lastStatusChange = $lastStatusChange;

        return $this;
    }

    public function getFailCount(): int
    {
        return $this->failCount;
    }

    public function incrementFailCount(): static
    {
        ++$this->failCount;

        return $this;
    }

    public function resetFailCount(): static
    {
        $this->failCount = 0;

        return $this;
    }

    public function transitionTo(CheckStatus $newStatus): void
    {
        if ($this->currentStatus !== $newStatus) {
            $this->currentStatus = $newStatus;
            $this->lastStatusChange = new \DateTimeImmutable();
            $this->failCount = 0;
        }

        if ($newStatus === CheckStatus::Fail) {
            $this->incrementFailCount();
        }
    }
}
