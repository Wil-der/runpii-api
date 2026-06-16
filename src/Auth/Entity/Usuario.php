<?php

declare(strict_types=1);

namespace App\Auth\Entity;

use App\Mensajero\Entity\Mensajero;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'usuarios')]
#[ORM\HasLifecycleCallbacks]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 36, unique: true)]
    private string $uuid;

    #[ORM\Column(length: 191, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column(enumType: TipoUsuario::class)]
    private TipoUsuario $tipo;

    #[ORM\Column(length: 120)]
    private string $nombre;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telefono = null;

    #[ORM\Column(options: ['default' => 1])]
    private bool $activo = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $deviceToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fotoPerfilPath = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    // Relaciones
    #[ORM\OneToOne(mappedBy: 'usuario', targetEntity: Mensajero::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?Mensajero $mensajero = null;

    public function __construct()
    {
        $this->uuid = $this->generateUuid();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
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

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function setPassword(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function getTipo(): TipoUsuario
    {
        return $this->tipo;
    }

    public function setTipo(TipoUsuario $tipo): self
    {
        $this->tipo = $tipo;
        return $this;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): self
    {
        $this->telefono = $telefono;
        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): self
    {
        $this->activo = $activo;
        return $this;
    }

    public function getDeviceToken(): ?string
    {
        return $this->deviceToken;
    }

    public function setDeviceToken(?string $deviceToken): self
    {
        $this->deviceToken = $deviceToken;
        return $this;
    }

    public function getFotoPerfilPath(): ?string
    {
        return $this->fotoPerfilPath;
    }

    public function setFotoPerfilPath(?string $fotoPerfilPath): self
    {
        $this->fotoPerfilPath = $fotoPerfilPath;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getMensajero(): ?Mensajero
    {
        return $this->mensajero;
    }

    public function setMensajero(?Mensajero $mensajero): self
    {
        if ($mensajero === null && $this->mensajero !== null) {
            $this->mensajero->setUsuario(null);
        }

        if ($mensajero !== null && $mensajero->getUsuario() !== $this) {
            $mensajero->setUsuario($this);
        }

        $this->mensajero = $mensajero;
        return $this;
    }

    // Métodos de UserInterface
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->tipo === TipoUsuario::ADMIN) {
            $roles[] = 'ROLE_ADMIN';
        }

        if ($this->tipo === TipoUsuario::MENSAJERO) {
            $roles[] = 'ROLE_MENSAJERO';
        }

        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
        // No se necesita limpiar datos sensibles temporales
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
