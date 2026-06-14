<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ClientUrlRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientUrlRepository::class)]
#[ORM\Table(name: 'client_urls')]
class ClientUrl
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'urls')]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\Column(length: 2048)]
    private string $url;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $basicAuthUser = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $basicAuthPassword = null;

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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label ?: null;

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

    public function hasBasicAuth(): bool
    {
        return null !== $this->basicAuthUser && null !== $this->basicAuthPassword;
    }

    public function getDisplayLabel(): string
    {
        return $this->label ?? $this->url;
    }

    public function getHostname(): string
    {
        $parsed = parse_url($this->url);

        return $parsed['host'] ?? $this->url;
    }

    public function __toString(): string
    {
        return $this->getDisplayLabel();
    }
}
