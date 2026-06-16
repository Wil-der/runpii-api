<?php

declare(strict_types=1);

namespace App\Pago\Entity;

use App\Envio\Entity\Envio;
use App\Auth\Entity\Usuario;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Pago\Repository\PagoRepository::class)]
#[ORM\Table(name: 'pagos')]
#[ORM\Index(columns: ['estado'])]
class Pago
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Envio::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Envio $envio;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $monto;

    #[ORM\Column(type: 'string', enumType: MetodoPago::class)]
    private MetodoPago $metodo;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referenciaCliente = null;

    #[ORM\Column(nullable: true)]
    private ?int $fotoComprobanteId = null;

    #[ORM\Column(type: 'string', enumType: EstadoPago::class)]
    private EstadoPago $estado = EstadoPago::PENDIENTE;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(name: 'verificado_por', nullable: true)]
    private ?Usuario $verificadoPor = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $verificadoAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motivoRechazo = null;

    #[ORM\Column]
    private \DateTimeInterface $createdAt;

    #[ORM\Column]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getMonto(): string
    {
        return $this->monto;
    }

    public function setMonto(string $monto): self
    {
        $this->monto = $monto;
        return $this;
    }

    public function getMetodo(): MetodoPago
    {
        return $this->metodo;
    }

    public function setMetodo(MetodoPago $metodo): self
    {
        $this->metodo = $metodo;
        return $this;
    }

    public function getReferenciaCliente(): ?string
    {
        return $this->referenciaCliente;
    }

    public function setReferenciaCliente(?string $referenciaCliente): self
    {
        $this->referenciaCliente = $referenciaCliente;
        return $this;
    }

    public function getFotoComprobanteId(): ?int
    {
        return $this->fotoComprobanteId;
    }

    public function setFotoComprobanteId(?int $fotoComprobanteId): self
    {
        $this->fotoComprobanteId = $fotoComprobanteId;
        return $this;
    }

    public function getEstado(): EstadoPago
    {
        return $this->estado;
    }

    public function setEstado(EstadoPago $estado): self
    {
        $this->estado = $estado;
        if ($estado === EstadoPago::VERIFICADO && $this->verificadoAt === null) {
            $this->verificadoAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getVerificadoPor(): ?Usuario
    {
        return $this->verificadoPor;
    }

    public function setVerificadoPor(?Usuario $verificadoPor): self
    {
        $this->verificadoPor = $verificadoPor;
        return $this;
    }

    public function getVerificadoAt(): ?\DateTimeInterface
    {
        return $this->verificadoAt;
    }

    public function getMotivoRechazo(): ?string
    {
        return $this->motivoRechazo;
    }

    public function setMotivoRechazo(?string $motivoRechazo): self
    {
        $this->motivoRechazo = $motivoRechazo;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }
}
