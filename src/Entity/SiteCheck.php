<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteCheckRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteCheckRepository::class)]
#[ORM\Table(name: 'site_checks')]
class SiteCheck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'checks')]
    #[ORM\JoinColumn(nullable: false)]
    private Site $site;

    #[ORM\Column(length: 64)]
    private string $type;

    /** Check-type-specific configuration (e.g. {"container_name": "my-app"}) */
    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $config = [];

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private int $checkIntervalMinutes = 5;

    /** @var Collection<int, CheckResult> */
    #[ORM\OneToMany(mappedBy: 'check', targetEntity: CheckResult::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['checkedAt' => 'DESC'])]
    private Collection $results;

    #[ORM\OneToOne(mappedBy: 'check', targetEntity: AlertState::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?AlertState $alertState = null;

    public function __construct()
    {
        $this->results = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function setSite(Site $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    /** @param array<string, mixed> $config */
    public function setConfig(array $config): static
    {
        $this->config = $config;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCheckIntervalMinutes(): int
    {
        return $this->checkIntervalMinutes;
    }

    public function setCheckIntervalMinutes(int $checkIntervalMinutes): static
    {
        $this->checkIntervalMinutes = $checkIntervalMinutes;

        return $this;
    }

    /** @return Collection<int, CheckResult> */
    public function getResults(): Collection
    {
        return $this->results;
    }

    public function getAlertState(): ?AlertState
    {
        return $this->alertState;
    }

    public function setAlertState(?AlertState $alertState): static
    {
        if ($alertState === null && $this->alertState !== null) {
            $this->alertState->setCheck(null);
        }

        if ($alertState !== null && $alertState->getCheck() !== $this) {
            $alertState->setCheck($this);
        }

        $this->alertState = $alertState;

        return $this;
    }

    public function getLabel(): string
    {
        return match ($this->type) {
            'http' => 'HTTP Reachability',
            'docker' => sprintf('Docker Container Health: %s', $this->config['container_name'] ?? 'unknown'),
            default => ucfirst($this->type),
        };
    }
}
