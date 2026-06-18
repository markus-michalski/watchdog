<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CheckRunner;
use App\Repository\SiteCheckRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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
    private Client $client;

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

    /** HH:MM — if set, run once daily at this time instead of using the interval */
    #[ORM\Column(length: 5, nullable: true)]
    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Time must be in HH:MM format (e.g. 08:30).')]
    private ?string $runAtTime = null;

    /** Keep results for N days; null = keep forever */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'Retention must be at least 1 day.')]
    private ?int $retentionDays = null;

    #[ORM\Column(enumType: CheckRunner::class, length: 16)]
    private CheckRunner $runner = CheckRunner::Dashboard;

    #[ORM\Column(options: ['default' => false])]
    private bool $runNow = false;

    #[ORM\ManyToOne(inversedBy: 'checks')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Agent $agent = null;

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

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client): static
    {
        $this->client = $client;

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

    public function getRunAtTime(): ?string
    {
        return $this->runAtTime;
    }

    public function setRunAtTime(?string $runAtTime): static
    {
        if (null !== $runAtTime && '' !== $runAtTime) {
            // <input type="time"> may submit HH:MM:SS — strip seconds, keep HH:MM
            $this->runAtTime = substr($runAtTime, 0, 5);
        } else {
            $this->runAtTime = null;
        }

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
        if (null === $alertState && null !== $this->alertState) {
            $this->alertState->setCheck(null);
        }

        if (null !== $alertState && $alertState->getCheck() !== $this) {
            $alertState->setCheck($this);
        }

        $this->alertState = $alertState;

        return $this;
    }

    public function getRetentionDays(): ?int
    {
        return $this->retentionDays;
    }

    public function setRetentionDays(?int $retentionDays): static
    {
        $this->retentionDays = $retentionDays;

        return $this;
    }

    public function getRunner(): CheckRunner
    {
        return $this->runner;
    }

    public function setRunner(CheckRunner $runner): static
    {
        $this->runner = $runner;

        return $this;
    }

    public function isRunNow(): bool
    {
        return $this->runNow;
    }

    public function setRunNow(bool $runNow): static
    {
        $this->runNow = $runNow;

        return $this;
    }

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function setAgent(?Agent $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function getLabel(): string
    {
        return match ($this->type) {
            'http' => 'HTTP Reachability',
            'docker' => 'Docker Container Health',
            'docker_exec' => 'Docker Exec',
            'file_age' => 'File Age',
            default => ucfirst($this->type),
        };
    }
}
