<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AgentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentRepository::class)]
#[ORM\Table(name: 'agents')]
class Agent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    /** SHA-256 hex hash of the raw bearer token */
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, SiteCheck> */
    #[ORM\OneToMany(mappedBy: 'agent', targetEntity: SiteCheck::class)]
    private Collection $checks;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->checks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function verifyToken(string $rawToken): bool
    {
        return hash_equals($this->tokenHash, hash('sha256', $rawToken));
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function markSeen(): static
    {
        $this->lastSeenAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, SiteCheck> */
    public function getChecks(): Collection
    {
        return $this->checks;
    }

    public function isOnline(int $thresholdMinutes = 10): bool
    {
        if (null === $this->lastSeenAt) {
            return false;
        }

        return $this->lastSeenAt > new \DateTimeImmutable("-{$thresholdMinutes} minutes");
    }
}
