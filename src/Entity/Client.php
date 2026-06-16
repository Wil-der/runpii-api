<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Client extends User
{
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?string $companyName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?string $taxId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?array $defaultAddresses = null;

    public function __construct()
    {
        parent::__construct();
        $this->setRoles(['ROLE_CLIENT']);
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): static
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): static
    {
        $this->taxId = $taxId;
        return $this;
    }

    public function getDefaultAddresses(): ?array
    {
        return $this->defaultAddresses;
    }

    public function setDefaultAddresses(?array $defaultAddresses): static
    {
        $this->defaultAddresses = $defaultAddresses;
        return $this;
    }

    public function addDefaultAddress(array $address): static
    {
        if ($this->defaultAddresses === null) {
            $this->defaultAddresses = [];
        }
        $this->defaultAddresses[] = $address;
        return $this;
    }
}
