<?php

declare(strict_types=1);

namespace App\Admin\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Admin\Repository\ConfiguracionRepository::class)]
#[ORM\Table(name: 'configuraciones')]
class Configuracion
{
    #[ORM\Id]
    #[ORM\Column(length: 80)]
    private string $clave;

    #[ORM\Column(length: 255)]
    private string $valor;

    #[ORM\Column(length: 300, nullable: true)]
    private ?string $descripcion = null;

    // Getters y Setters
    public function getClave(): string
    {
        return $this->clave;
    }

    public function setClave(string $clave): self
    {
        $this->clave = $clave;
        return $this;
    }

    public function getValor(): string
    {
        return $this->valor;
    }

    public function setValor(string $valor): self
    {
        $this->valor = $valor;
        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): self
    {
        $this->descripcion = $descripcion;
        return $this;
    }

    /**
     * Convierte el valor a entero si es posible
     */
    public function getValorAsInt(): int
    {
        return (int) $this->valor;
    }

    /**
     * Convierte el valor a float si es posible
     */
    public function getValorAsFloat(): float
    {
        return (float) $this->valor;
    }

    /**
     * Convierte el valor a booleano si es posible
     */
    public function getValorAsBool(): bool
    {
        return in_array(strtolower($this->valor), ['1', 'true', 'yes', 'si'], true);
    }
}
