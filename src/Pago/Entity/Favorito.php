<?php

declare(strict_types=1);

namespace App\Pago\Entity;

use App\Auth\Entity\Usuario;
use App\Mensajero\Entity\Mensajero;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Pago\Repository\FavoritoRepository::class)]
#[ORM\Table(name: 'favoritos')]
#[ORM\UniqueConstraint(columns: ['cliente_id', 'mensajero_id'])]
class Favorito
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Usuario $cliente;

    #[ORM\ManyToOne(targetEntity: Mensajero::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Mensajero $mensajero;

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

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
