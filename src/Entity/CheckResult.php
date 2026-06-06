<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CheckStatus;
use App\Repository\CheckResultRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CheckResultRepository::class)]
#[ORM\Table(name: 'check_results')]
class CheckResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'results')]
    #[ORM\JoinColumn(nullable: false)]
    private SiteCheck $check;

    #[ORM\Column(enumType: CheckStatus::class)]
    private CheckStatus $status;

    #[ORM\Column(nullable: true)]
    private ?int $statusCode = null;

    #[ORM\Column(nullable: true)]
    private ?int $responseTimeMs = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $message = null;

    #[ORM\Column]
    private \DateTimeImmutable $checkedAt;

    public function __construct()
    {
        $this->checkedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCheck(): SiteCheck
    {
        return $this->check;
    }

    public function setCheck(SiteCheck $check): static
    {
        $this->check = $check;

        return $this;
    }

    public function getStatus(): CheckStatus
    {
        return $this->status;
    }

    public function setStatus(CheckStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function setStatusCode(?int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getResponseTimeMs(): ?int
    {
        return $this->responseTimeMs;
    }

    public function setResponseTimeMs(?int $responseTimeMs): static
    {
        $this->responseTimeMs = $responseTimeMs;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getCheckedAt(): \DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function isOk(): bool
    {
        return $this->status === CheckStatus::Ok;
    }
}
