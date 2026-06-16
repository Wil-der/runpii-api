<?php

declare(strict_types=1);

namespace App\Chat\Entity;

use App\Envio\Entity\Envio;
use App\Auth\Entity\Usuario;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Chat\Repository\MensajeChatRepository::class)]
#[ORM\Table(name: 'mensajes_chat')]
#[ORM\Index(columns: ['envio_id', 'created_at'])]
#[ORM\Index(columns: ['expira_at'])]
class MensajeChat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Envio::class, inversedBy: 'mensajesChats')]
    #[ORM\JoinColumn(nullable: false)]
    private Envio $envio;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(name: 'remitente_id', nullable: true)]
    private ?Usuario $remitente = null;

    #[ORM\Column(type: 'string', enumType: TipoMensaje::class)]
    private TipoMensaje $tipo = TipoMensaje::TEXTO;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contenido = null;

    #[ORM\Column]
    private bool $leidoCliente = false;

    #[ORM\Column]
    private bool $leidoMensajero = false;

    #[ORM\Column]
    private bool $leidoDestinatario = false;

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

    public function getRemitente(): ?Usuario
    {
        return $this->remitente;
    }

    public function setRemitente(?Usuario $remitente): self
    {
        $this->remitente = $remitente;
        return $this;
    }

    public function getTipo(): TipoMensaje
    {
        return $this->tipo;
    }

    public function setTipo(TipoMensaje $tipo): self
    {
        $this->tipo = $tipo;
        return $this;
    }

    public function getContenido(): ?string
    {
        return $this->contenido;
    }

    public function setContenido(?string $contenido): self
    {
        $this->contenido = $contenido;
        return $this;
    }

    public function isLeidoCliente(): bool
    {
        return $this->leidoCliente;
    }

    public function setLeidoCliente(bool $leidoCliente): self
    {
        $this->leidoCliente = $leidoCliente;
        return $this;
    }

    public function isLeidoMensajero(): bool
    {
        return $this->leidoMensajero;
    }

    public function setLeidoMensajero(bool $leidoMensajero): self
    {
        $this->leidoMensajero = $leidoMensajero;
        return $this;
    }

    public function isLeidoDestinatario(): bool
    {
        return $this->leidoDestinatario;
    }

    public function setLeidoDestinatario(bool $leidoDestinatario): self
    {
        $this->leidoDestinatario = $leidoDestinatario;
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
