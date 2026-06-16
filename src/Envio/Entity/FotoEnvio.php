<?php

declare(strict_types=1);

namespace App\Envio\Entity;

use App\Envio\Entity\Envio;
use App\Envio\Entity\TipoFotoEnvio;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Envio\Repository\FotoEnvioRepository::class)]
#[ORM\Table(name: 'fotos_envio')]
class FotoEnvio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Envio::class, inversedBy: 'fotosEnvios')]
    #[ORM\JoinColumn(nullable: false)]
    private Envio $envio;

    #[ORM\Column(type: 'string', enumType: TipoFotoEnvio::class)]
    private TipoFotoEnvio $tipo;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 64)]
    private string $hashSha256;

    #[ORM\Column]
    private int $subidoPor;

    #[ORM\Column]
    private \DateTimeInterface $createdAt;

    #[ORM\Column]
    private \DateTimeInterface $expiresAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters y Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnvio(): Envio
    {
        return $this->envio;
    }

    public function setEnvio(Envio $envio): self
    {
        $this->envio = $envio;
        return $this;
    }

    public function getTipo(): TipoFotoEnvio
    {
        return $this->tipo;
    }

    public function setTipo(TipoFotoEnvio $tipo): self
    {
        $this->tipo = $tipo;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getHashSha256(): string
    {
        return $this->hashSha256;
    }

    public function setHashSha256(string $hashSha256): self
    {
        $this->hashSha256 = $hashSha256;
        return $this;
    }

    public function getSubidoPor(): int
    {
        return $this->subidoPor;
    }

    public function setSubidoPor(int $subidoPor): self
    {
        $this->subidoPor = $subidoPor;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }
}
