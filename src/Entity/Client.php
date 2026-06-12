<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'clients')]
#[ORM\HasLifecycleCallbacks]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Contact> */
    #[ORM\ManyToMany(targetEntity: Contact::class, inversedBy: 'clients')]
    #[ORM\JoinTable(name: 'client_contacts')]
    private Collection $contacts;

    /** @var Collection<int, SiteCheck> */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: SiteCheck::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $checks;

    /** @var Collection<int, ClientUrl> */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: ClientUrl::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['url' => 'ASC'])]
    private Collection $urls;

    public function __construct()
    {
        $this->contacts = new ArrayCollection();
        $this->checks = new ArrayCollection();
        $this->urls = new ArrayCollection();
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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
            $check->setClient($this);
        }

        return $this;
    }

    public function removeCheck(SiteCheck $check): static
    {
        $this->checks->removeElement($check);

        return $this;
    }

    /** @return Collection<int, ClientUrl> */
    public function getUrls(): Collection
    {
        return $this->urls;
    }

    public function addUrl(ClientUrl $url): static
    {
        if (!$this->urls->contains($url)) {
            $this->urls->add($url);
            $url->setClient($this);
        }

        return $this;
    }

    public function removeUrl(ClientUrl $url): static
    {
        $this->urls->removeElement($url);

        return $this;
    }
}
