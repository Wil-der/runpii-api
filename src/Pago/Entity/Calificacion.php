<?php

declare(strict_types=1);

namespace App\Pago\Entity;

use App\Envio\Entity\Envio;
use App\Auth\Entity\Usuario;
use App\Mensajero\Entity\Mensajero;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Pago\Repository\CalificacionRepository::class)]
#[ORM\Table(name: 'calificaciones')]
#[ORM\Index(columns: ['mensajero_id'])]
#[ORM\UniqueConstraint(columns: ['envio_id'])]
class Calificacion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Envio::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Envio $envio;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Usuario $cliente;

    #[ORM\ManyToOne(targetEntity: Mensajero::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Mensajero $mensajero;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 1, max: 5)]
    private int $estrellas;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $comentario = null;

    #[ORM\Column]
    private \DateTimeInterface $createdAt;

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

    public function getCliente(): Usuario
    {
        return $this->cliente;
    }

    public function setCliente(Usuario $cliente): self
    {
        $this->cliente = $cliente;
        return $this;
    }

    public function getMensajero(): Mensajero
    {
        return $this->mensajero;
    }

    public function setMensajero(Mensajero $mensajero): self
    {
        $this->mensajero = $mensajero;
        return $this;
    }

    public function getEstrellas(): int
    {
        return $this->estrellas;
    }

    public function setEstrellas(int $estrellas): self
    {
        if ($estrellas < 1 || $estrellas > 5) {
            throw new \InvalidArgumentException('Las estrellas deben estar entre 1 y 5');
        }
        $this->estrellas = $estrellas;
        return $this;
    }

    public function getComentario(): ?string
    {
        return $this->comentario;
    }

    public function setComentario(?string $comentario): self
    {
        $this->comentario = $comentario;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
