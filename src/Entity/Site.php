<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
#[ORM\Table(name: 'sites')]
#[ORM\HasLifecycleCallbacks]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 2048)]
    private string $url;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $basicAuthUser = null;

    /** Stored encrypted via lifecycle callbacks */
    #[ORM\Column(length: 512, nullable: true)]
    private ?string $basicAuthPassword = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private int $checkIntervalMinutes = 5;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'site', targetEntity: Contact::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $contacts;

    #[ORM\OneToMany(mappedBy: 'site', targetEntity: SiteCheck::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $checks;

    public function __construct()
    {
        $this->contacts = new ArrayCollection();
        $this->checks = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getBasicAuthUser(): ?string
    {
        return $this->basicAuthUser;
    }

    public function setBasicAuthUser(?string $basicAuthUser): static
    {
        $this->basicAuthUser = $basicAuthUser;

        return $this;
    }

    public function getBasicAuthPassword(): ?string
    {
        return $this->basicAuthPassword;
    }

    public function setBasicAuthPassword(?string $basicAuthPassword): static
    {
        $this->basicAuthPassword = $basicAuthPassword;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, Contact> */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setSite($this);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        $this->contacts->removeElement($contact);

        return $this;
    }

    /** @return Collection<int, SiteCheck> */
    public function getChecks(): Collection
    {
        return $this->checks;
    }

    public function addCheck(SiteCheck $check): static
    {
        if (!$this->checks->contains($check)) {
            $this->checks->add($check);
            $check->setSite($this);
        }

        return $this;
    }

    public function removeCheck(SiteCheck $check): static
    {
        $this->checks->removeElement($check);

        return $this;
    }

    public function hasBasicAuth(): bool
    {
        return $this->basicAuthUser !== null && $this->basicAuthPassword !== null;
    }
}
