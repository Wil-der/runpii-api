<?php

declare(strict_types=1);

namespace App\Tracking\Entity;

use App\Mensajero\Entity\Mensajero;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Tracking\Repository\UbicacionMensajeroRepository::class)]
#[ORM\Table(name: 'ubicaciones_mensajero')]
#[ORM\Index(columns: ['mensajero_id', 'registrado_at'], flags: ['DESC'])]
class UbicacionMensajero
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Mensajero::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Mensajero $mensajero;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    private string $lat;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    private string $lng;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $precisionM = null;

    #[ORM\Column]
    private \DateTimeInterface $registradoAt;

    public function __construct()
    {
        $this->registradoAt = new \DateTimeImmutable();
    }

    // Getters y Setters
    public function getId(): ?int
    {
        return $this->id;
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

    public function getLat(): string
    {
        return $this->lat;
    }

    public function setLat(string $lat): self
    {
        $this->lat = $lat;
        return $this;
    }

    public function getLng(): string
    {
        return $this->lng;
    }

    public function setLng(string $lng): self
    {
        $this->lng = $lng;
        return $this;
    }

    public function getPrecisionM(): ?int
    {
        return $this->precisionM;
    }

    public function setPrecisionM(?int $precisionM): self
    {
        $this->precisionM = $precisionM;
        return $this;
    }

    public function getRegistradoAt(): \DateTimeInterface
    {
        return $this->registradoAt;
    }
}
