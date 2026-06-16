<?php

declare(strict_types=1);

namespace App\Mensajero\Entity;

use App\Auth\Entity\Usuario;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mensajeros')]
#[ORM\HasLifecycleCallbacks]
class Mensajero
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'mensajero', targetEntity: Usuario::class)]
    #[ORM\JoinColumn(name: 'usuario_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Usuario $usuario;

    #[ORM\Column(type: 'binary', length: 512)]
    private string $ciCifrado;

    #[ORM\Column(type: 'binary', length: 512)]
    private string $tarjetaBancariaCifrada;

    #[ORM\Column(type: 'binary', length: 512)]
    private string $movilPagosCifrado;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fotoDocumentoPath = null;

    #[ORM\Column(length: 60, options: ['default' => 'moto'])]
    private string $tipoVehiculo = 'moto';

    #[ORM\Column(enumType: EstadoAprobacion::class, options: ['default' => 'pendiente'])]
    private EstadoAprobacion $estadoAprobacion = EstadoAprobacion::PENDIENTE;

    #[ORM\Column(options: ['default' => 0])]
    private bool $disponible = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $bloqueadoHasta = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $rechazosConsecutivos = 0;

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, options: ['default' => 0.00])]
    private string $calificacionPromedio = '0.00';

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 0])]
    private int $totalEnvios = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ultimoHeartbeat = null;

    public function __construct(Usuario $usuario)
    {
        $this->usuario = $usuario;
        $this->estadoAprobacion = EstadoAprobacion::PENDIENTE;
    }

    /**
     * Registra un heartbeat del mensajero.
     * Un mensajero se considera disponible solo si ha enviado heartbeat en los últimos 2 minutos.
     */
    public function registrarHeartbeat(): void
    {
        $this->ultimoHeartbeat = new \DateTimeImmutable();
    }

    public function getUltimoHeartbeat(): ?\DateTimeImmutable
    {
        return $this->ultimoHeartbeat;
    }

    /**
     * Verifica si el mensajero está activo basado en el último heartbeat.
     * Se considera inactivo si no ha enviado heartbeat en los últimos $segundosUmbral.
     */
    public function esActivo(int $segundosUmbral = 120): bool
    {
        if ($this->ultimoHeartbeat === null) {
            return false;
        }

        $ahora = new \DateTimeImmutable();
        $diferencia = $ahora->getTimestamp() - $this->ultimoHeartbeat->getTimestamp();

        return $diferencia <= $segundosUmbral;
    }

    /**
     * Establece la disponibilidad basada en el estado del heartbeat.
     * Solo puede estar disponible si está activo (heartbeat reciente).
     */
    public function actualizarDisponibilidadPorHeartbeat(int $segundosUmbral = 120): void
    {
        if ($this->esActivo($segundosUmbral) && $this->estadoAprobacion === EstadoAprobacion::APROBADO && ($this->bloqueadoHasta === null || $this->bloqueadoHasta < new \DateTimeImmutable())) {
            $this->disponible = true;
        } else {
            $this->disponible = false;
        }
    }

    // Getters y Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(?Usuario $usuario): self
    {
        $this->usuario = $usuario;
        return $this;
    }

    public function getCiCifrado(): string
    {
        return $this->ciCifrado;
    }

    public function setCiCifrado(string $ciCifrado): self
    {
        $this->ciCifrado = $ciCifrado;
        return $this;
    }

    public function getTarjetaBancariaCifrada(): string
    {
        return $this->tarjetaBancariaCifrada;
    }

    public function setTarjetaBancariaCifrada(string $tarjetaBancariaCifrada): self
    {
        $this->tarjetaBancariaCifrada = $tarjetaBancariaCifrada;
        return $this;
    }

    public function getMovilPagosCifrado(): string
    {
        return $this->movilPagosCifrado;
    }

    public function setMovilPagosCifrado(string $movilPagosCifrado): self
    {
        $this->movilPagosCifrado = $movilPagosCifrado;
        return $this;
    }

    public function getFotoDocumentoPath(): ?string
    {
        return $this->fotoDocumentoPath;
    }

    public function setFotoDocumentoPath(?string $fotoDocumentoPath): self
    {
        $this->fotoDocumentoPath = $fotoDocumentoPath;
        return $this;
    }

    public function getTipoVehiculo(): string
    {
        return $this->tipoVehiculo;
    }

    public function setTipoVehiculo(string $tipoVehiculo): self
    {
        $this->tipoVehiculo = $tipoVehiculo;
        return $this;
    }

    public function getEstadoAprobacion(): EstadoAprobacion
    {
        return $this->estadoAprobacion;
    }

    public function setEstadoAprobacion(EstadoAprobacion $estadoAprobacion): self
    {
        $this->estadoAprobacion = $estadoAprobacion;
        return $this;
    }

    public function isDisponible(): bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): self
    {
        $this->disponible = $disponible;
        return $this;
    }

    public function getBloqueadoHasta(): ?\DateTimeImmutable
    {
        return $this->bloqueadoHasta;
    }

    public function setBloqueadoHasta(?\DateTimeImmutable $bloqueadoHasta): self
    {
        $this->bloqueadoHasta = $bloqueadoHasta;
        return $this;
    }

    public function getRechazosConsecutivos(): int
    {
        return $this->rechazosConsecutivos;
    }

    public function setRechazosConsecutivos(int $rechazosConsecutivos): self
    {
        $this->rechazosConsecutivos = $rechazosConsecutivos;
        return $this;
    }

    public function getCalificacionPromedio(): string
    {
        return $this->calificacionPromedio;
    }

    public function setCalificacionPromedio(string $calificacionPromedio): self
    {
        $this->calificacionPromedio = $calificacionPromedio;
        return $this;
    }

    public function getTotalEnvios(): int
    {
        return $this->totalEnvios;
    }

    public function setTotalEnvios(int $totalEnvios): self
    {
        $this->totalEnvios = $totalEnvios;
        return $this;
    }

    public function incrementarRechazo(): void
    {
        $this->rechazosConsecutivos++;
        
        // Bloquear temporalmente después de 3 rechazos consecutivos
        if ($this->rechazosConsecutivos >= 3) {
            $this->bloqueadoHasta = new \DateTimeImmutable('+24 hours');
            $this->disponible = false;
        }
    }

    public function resetearRechazos(): void
    {
        $this->rechazosConsecutivos = 0;
        $this->bloqueadoHasta = null;
    }

    public function incrementarTotalEnvios(): void
    {
        $this->totalEnvios++;
    }
}
