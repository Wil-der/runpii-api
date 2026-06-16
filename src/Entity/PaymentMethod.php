<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PaymentMethodRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentMethodRepository::class)]
#[ORM\Table(name: 'payment_method')]
class PaymentMethod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['credit_card', 'debit_card', 'paypal', 'bank_transfer', 'cash'])]
    #[Groups(['api:read', 'api:write'])]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?string $providerAccountId = null;

    #[ORM\Column(length: 4, nullable: true)]
    #[Groups(['api:read'])]
    private ?string $lastFourDigits = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?string $cardBrand = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?int $expirationMonth = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?int $expirationYear = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?bool $isDefault = null;

    #[ORM\Column]
    #[Groups(['api:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'paymentMethods')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['api:read'])]
    private ?User $user = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isDefault = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getProviderAccountId(): ?string
    {
        return $this->providerAccountId;
    }

    public function setProviderAccountId(?string $providerAccountId): static
    {
        $this->providerAccountId = $providerAccountId;
        return $this;
    }

    public function getLastFourDigits(): ?string
    {
        return $this->lastFourDigits;
    }

    public function setLastFourDigits(?string $lastFourDigits): static
    {
        $this->lastFourDigits = $lastFourDigits;
        return $this;
    }

    public function getCardBrand(): ?string
    {
        return $this->cardBrand;
    }

    public function setCardBrand(?string $cardBrand): static
    {
        $this->cardBrand = $cardBrand;
        return $this;
    }

    public function getExpirationMonth(): ?int
    {
        return $this->expirationMonth;
    }

    public function setExpirationMonth(?int $expirationMonth): static
    {
        $this->expirationMonth = $expirationMonth;
        return $this;
    }

    public function getExpirationYear(): ?int
    {
        return $this->expirationYear;
    }

    public function setExpirationYear(?int $expirationYear): static
    {
        $this->expirationYear = $expirationYear;
        return $this;
    }

    public function isDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(?bool $isDefault): static
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
