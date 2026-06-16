<?php

declare(strict_types=1);

namespace App\Envio\Entity;

use App\Auth\Entity\Usuario;
use App\Mensajero\Entity\Mensajero;
use App\Envio\Entity\EstadoEnvio;
use App\Envio\Entity\TipoEnvio;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Envio\Repository\EnvioRepository::class)]
#[ORM\Table(name: 'envios')]
#[ORM\HasLifecycleCallbacks]
class Envio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $uuid;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Usuario $cliente;

    #[ORM\ManyToOne(targetEntity: Mensajero::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Mensajero $mensajero = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(name: 'destinatario_usuario_id', nullable: true)]
    private ?Usuario $destinatarioUsuario = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $origenDireccion;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    #[Assert\NotBlank]
    #[Assert\Range(min: -90, max: 90)]
    private string $origenLat;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    #[Assert\NotBlank]
    #[Assert\Range(min: -180, max: 180)]
    private string $origenLng;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $destinoDireccion;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    #[Assert\NotBlank]
    #[Assert\Range(min: -90, max: 90)]
    private string $destinoLat;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    #[Assert\NotBlank]
    #[Assert\Range(min: -180, max: 180)]
    private string $destinoLng;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true], nullable: true)]
    private ?int $rutaDistanciaM = null;

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true], nullable: true)]
    private ?int $rutaDuracionS = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rutaPolyline = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $destNombre;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    private string $destTelefono;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $pesoAproxKg = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $precioBase = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $comisionPlataforma = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $gananciaMensajero = '0.00';

    #[ORM\Column(type: 'string', enumType: EstadoEnvio::class)]
    private EstadoEnvio $estado = EstadoEnvio::PENDIENTE;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motivoCancelacion = null;

    #[ORM\Column(type: 'string', enumType: CanceladoPor::class, nullable: true)]
    private ?CanceladoPor $canceladoPor = null;

    #[ORM\Column(type: 'string', enumType: TipoEnvio::class)]
    private TipoEnvio $tipoEnvio = TipoEnvio::INMEDIATO;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $fechaPlanificada = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $ventanaInicio = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $ventanaFin = null;

    #[ORM\Column(length: 6)]
    private string $codigoVerificacion;

    #[ORM\Column]
    private bool $codigoUsado = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $asignadoAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $recogidoAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $entregadoAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $canceladoAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $expiraAsignacionAt = null;

    #[ORM\Column]
    private \DateTimeInterface $createdAt;

    #[ORM\Column]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: 'envio', targetEntity: \App\Envio\Entity\FotoEnvio::class)]
    private Collection $fotosEnvios;

    #[ORM\OneToOne(mappedBy: 'envio', targetEntity: \App\Pago\Entity\Pago::class)]
    private ?\App\Pago\Entity\Pago $pago = null;

    #[ORM\OneToOne(mappedBy: 'envio', targetEntity: \App\Pago\Entity\Calificacion::class)]
    private ?\App\Pago\Entity\Calificacion $calificacion = null;

    #[ORM\OneToMany(mappedBy: 'envio', targetEntity: \App\Chat\Entity\MensajeChat::class)]
    private Collection $mensajesChats;

    public function __construct()
    {
        $this->uuid = Uuid::v4();
        $this->estado = EstadoEnvio::PENDIENTE;
        $this->tipoEnvio = TipoEnvio::INMEDIATO;
        $this->codigoVerificacion = $this->generarCodigoVerificacion();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->fotosEnvios = new ArrayCollection();
        $this->mensajesChats = new ArrayCollection();
    }

    private function generarCodigoVerificacion(): string
    {
        return strtoupper(substr(str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT), 0, 6));
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

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getUuidString(): string
    {
        return $this->uuid->toRfc4122();
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

    public function getMensajero(): ?Mensajero
    {
        return $this->mensajero;
    }

    public function setMensajero(?Mensajero $mensajero): self
    {
        $this->mensajero = $mensajero;
        if ($mensajero !== null && $this->estado === EstadoEnvio::ASIGNADO) {
            $this->asignadoAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getDestinatarioUsuario(): ?Usuario
    {
        return $this->destinatarioUsuario;
    }

    public function setDestinatarioUsuario(?Usuario $destinatarioUsuario): self
    {
        $this->destinatarioUsuario = $destinatarioUsuario;
        return $this;
    }

    public function getOrigenDireccion(): string
    {
        return $this->origenDireccion;
    }

    public function setOrigenDireccion(string $origenDireccion): self
    {
        $this->origenDireccion = $origenDireccion;
        return $this;
    }

    public function getOrigenLat(): string
    {
        return $this->origenLat;
    }

    public function setOrigenLat(string $origenLat): self
    {
        $this->origenLat = $origenLat;
        return $this;
    }

    public function getOrigenLng(): string
    {
        return $this->origenLng;
    }

    public function setOrigenLng(string $origenLng): self
    {
        $this->origenLng = $origenLng;
        return $this;
    }

    public function getDestinoDireccion(): string
    {
        return $this->destinoDireccion;
    }

    public function setDestinoDireccion(string $destinoDireccion): self
    {
        $this->destinoDireccion = $destinoDireccion;
        return $this;
    }

    public function getDestinoLat(): string
    {
        return $this->destinoLat;
    }

    public function setDestinoLat(string $destinoLat): self
    {
        $this->destinoLat = $destinoLat;
        return $this;
    }

    public function getDestinoLng(): string
    {
        return $this->destinoLng;
    }

    public function setDestinoLng(string $destinoLng): self
    {
        $this->destinoLng = $destinoLng;
        return $this;
    }

    public function getRutaDistanciaM(): ?int
    {
        return $this->rutaDistanciaM;
    }

    public function setRutaDistanciaM(?int $rutaDistanciaM): self
    {
        $this->rutaDistanciaM = $rutaDistanciaM;
        return $this;
    }

    public function getRutaDuracionS(): ?int
    {
        return $this->rutaDuracionS;
    }

    public function setRutaDuracionS(?int $rutaDuracionS): self
    {
        $this->rutaDuracionS = $rutaDuracionS;
        return $this;
    }

    public function getRutaPolyline(): ?string
    {
        return $this->rutaPolyline;
    }

    public function setRutaPolyline(?string $rutaPolyline): self
    {
        $this->rutaPolyline = $rutaPolyline;
        return $this;
    }

    public function getDestNombre(): string
    {
        return $this->destNombre;
    }

    public function setDestNombre(string $destNombre): self
    {
        $this->destNombre = $destNombre;
        return $this;
    }

    public function getDestTelefono(): string
    {
        return $this->destTelefono;
    }

    public function setDestTelefono(string $destTelefono): self
    {
        $this->destTelefono = $destTelefono;
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

    public function getPesoAproxKg(): ?string
    {
        return $this->pesoAproxKg;
    }

    public function setPesoAproxKg(?string $pesoAproxKg): self
    {
        $this->pesoAproxKg = $pesoAproxKg;
        return $this;
    }

    public function getPrecioBase(): string
    {
        return $this->precioBase;
    }

    public function setPrecioBase(string $precioBase): self
    {
        $this->precioBase = $precioBase;
        return $this;
    }

    public function getComisionPlataforma(): string
    {
        return $this->comisionPlataforma;
    }

    public function setComisionPlataforma(string $comisionPlataforma): self
    {
        $this->comisionPlataforma = $comisionPlataforma;
        return $this;
    }

    public function getGananciaMensajero(): string
    {
        return $this->gananciaMensajero;
    }

    public function setGananciaMensajero(string $gananciaMensajero): self
    {
        $this->gananciaMensajero = $gananciaMensajero;
        return $this;
    }

    public function getEstado(): EstadoEnvio
    {
        return $this->estado;
    }

    public function setEstado(EstadoEnvio $estado): self
    {
        $this->estado = $estado;
        
        // Actualizar timestamps según el estado
        match ($estado) {
            EstadoEnvio::ASIGNADO => $this->asignadoAt = new \DateTimeImmutable(),
            EstadoEnvio::RECOGIDO => $this->recogidoAt = new \DateTimeImmutable(),
            EstadoEnvio::ENTREGADO => $this->entregadoAt = new \DateTimeImmutable(),
            EstadoEnvio::CANCELADO => $this->canceladoAt = new \DateTimeImmutable(),
            default => null,
        };
        
        return $this;
    }

    // Métodos adaptadores para el Workflow (trabaja con strings)
    public function getMarking(): string
    {
        return $this->estado->value;
    }

    public function setMarking(string $estado): void
    {
        $this->estado = EstadoEnvio::from($estado);
        // Actualizar timestamps según el estado
        match ($this->estado) {
            EstadoEnvio::ASIGNADO => $this->asignadoAt = new \DateTimeImmutable(),
            EstadoEnvio::RECOGIDO => $this->recogidoAt = new \DateTimeImmutable(),
            EstadoEnvio::ENTREGADO => $this->entregadoAt = new \DateTimeImmutable(),
            EstadoEnvio::CANCELADO => $this->canceladoAt = new \DateTimeImmutable(),
            default => null,
        };
    }

    public function getMotivoCancelacion(): ?string
    {
        return $this->motivoCancelacion;
    }

    public function setMotivoCancelacion(?string $motivoCancelacion): self
    {
        $this->motivoCancelacion = $motivoCancelacion;
        return $this;
    }

    public function getCanceladoPor(): ?CanceladoPor
    {
        return $this->canceladoPor;
    }

    public function setCanceladoPor(?CanceladoPor $canceladoPor): self
    {
        $this->canceladoPor = $canceladoPor;
        return $this;
    }

    public function getTipoEnvio(): TipoEnvio
    {
        return $this->tipoEnvio;
    }

    public function setTipoEnvio(TipoEnvio $tipoEnvio): self
    {
        $this->tipoEnvio = $tipoEnvio;
        return $this;
    }

    public function getFechaPlanificada(): ?\DateTimeInterface
    {
        return $this->fechaPlanificada;
    }

    public function setFechaPlanificada(?\DateTimeInterface $fechaPlanificada): self
    {
        $this->fechaPlanificada = $fechaPlanificada;
        return $this;
    }

    public function getVentanaInicio(): ?\DateTimeInterface
    {
        return $this->ventanaInicio;
    }

    public function setVentanaInicio(?\DateTimeInterface $ventanaInicio): self
    {
        $this->ventanaInicio = $ventanaInicio;
        return $this;
    }

    public function getVentanaFin(): ?\DateTimeInterface
    {
        return $this->ventanaFin;
    }

    public function setVentanaFin(?\DateTimeInterface $ventanaFin): self
    {
        $this->ventanaFin = $ventanaFin;
        return $this;
    }

    public function getCodigoVerificacion(): string
    {
        return $this->codigoVerificacion;
    }

    public function isCodigoUsado(): bool
    {
        return $this->codigoUsado;
    }

    public function setCodigoUsado(bool $codigoUsado): self
    {
        $this->codigoUsado = $codigoUsado;
        return $this;
    }

    public function getAsignadoAt(): ?\DateTimeInterface
    {
        return $this->asignadoAt;
    }

    public function getRecogidoAt(): ?\DateTimeInterface
    {
        return $this->recogidoAt;
    }

    public function getEntregadoAt(): ?\DateTimeInterface
    {
        return $this->entregadoAt;
    }

    public function getCanceladoAt(): ?\DateTimeInterface
    {
        return $this->canceladoAt;
    }

    public function getExpiraAsignacionAt(): ?\DateTimeInterface
    {
        return $this->expiraAsignacionAt;
    }

    public function setExpiraAsignacionAt(?\DateTimeInterface $expiraAsignacionAt): self
    {
        $this->expiraAsignacionAt = $expiraAsignacionAt;
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

    /**
     * @return Collection<int, FotoEnvio>
     */
    public function getFotosEnvios(): Collection
    {
        return $this->fotosEnvios;
    }

    public function addFotoEnvio(FotoEnvio $fotoEnvio): self
    {
        if (!$this->fotosEnvios->contains($fotoEnvio)) {
            $this->fotosEnvios->add($fotoEnvio);
            $fotoEnvio->setEnvio($this);
        }
        return $this;
    }

    public function removeFotoEnvio(FotoEnvio $fotoEnvio): self
    {
        if ($this->fotosEnvios->removeElement($fotoEnvio)) {
            if ($fotoEnvio->getEnvio() === $this) {
                $fotoEnvio->setEnvio(null);
            }
        }
        return $this;
    }

    public function getPago(): ?\App\Pago\Entity\Pago
    {
        return $this->pago;
    }

    public function setPago(?\App\Pago\Entity\Pago $pago): self
    {
        $this->pago = $pago;
        return $this;
    }

    public function getCalificacion(): ?\App\Pago\Entity\Calificacion
    {
        return $this->calificacion;
    }

    public function setCalificacion(?\App\Pago\Entity\Calificacion $calificacion): self
    {
        $this->calificacion = $calificacion;
        return $this;
    }

    /**
     * @return Collection<int, MensajeChat>
     */
    public function getMensajesChats(): Collection
    {
        return $this->mensajesChats;
    }

    public function addMensajeChat(\App\Chat\Entity\MensajeChat $mensajeChat): self
    {
        if (!$this->mensajesChats->contains($mensajeChat)) {
            $this->mensajesChats->add($mensajeChat);
            $mensajeChat->setEnvio($this);
        }
        return $this;
    }

    public function removeMensajeChat(\App\Chat\Entity\MensajeChat $mensajeChat): self
    {
        if ($this->mensajesChats->removeElement($mensajeChat)) {
            if ($mensajeChat->getEnvio() === $this) {
                $mensajeChat->setEnvio(null);
            }
        }
        return $this;
    }
}
