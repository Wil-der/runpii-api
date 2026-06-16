<?php

declare(strict_types=1);

namespace App\Mensajero\Service;

use App\Mensajero\Entity\Mensajero;
use App\Mensajero\Entity\EstadoAprobacion;
use App\Mensajero\Repository\MensajeroRepository;
use App\Auth\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MensajeroService
{
    public function __construct(
        private readonly MensajeroRepository $mensajeroRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * Crea un perfil de mensajero para un usuario.
     */
    public function crearPerfilMensajero(
        Usuario $usuario,
        string $ciCifrado,
        string $tarjetaBancariaCifrada,
        string $movilPagosCifrado,
        string $tipoVehiculo = 'moto'
    ): Mensajero {
        $mensajero = new Mensajero($usuario);
        $mensajero->setCiCifrado($ciCifrado);
        $mensajero->setTarjetaBancariaCifrada($tarjetaBancariaCifrada);
        $mensajero->setMovilPagosCifrado($movilPagosCifrado);
        $mensajero->setTipoVehiculo($tipoVehiculo);

        $this->mensajeroRepository->save($mensajero, true);

        return $mensajero;
    }

    /**
     * Actualiza la foto del documento (CI) del mensajero.
     * Solo permite cambiar la foto si el mensajero aún no ha sido aprobado.
     * 
     * @throws \RuntimeException Si el mensajero ya fue aprobado
     */
    public function actualizarFotoDocumento(Mensajero $mensajero, UploadedFile $foto, string $uploadDir): string
    {
        if ($mensajero->getEstadoAprobacion() === EstadoAprobacion::APROBADO) {
            throw new \RuntimeException('No se puede modificar la foto del documento una vez aprobado el mensajero.');
        }

        // Generar nombre único con hash SHA-256
        $hash = hash_file('sha256', $foto->getPathname());
        $extension = $foto->guessExtension() ?? 'jpg';
        $nombreArchivo = $hash . '.' . $extension;
        
        // Mover archivo al directorio de uploads
        $foto->move($uploadDir, $nombreArchivo);
        
        $rutaCompleta = $uploadDir . '/' . $nombreArchivo;
        $mensajero->setFotoDocumentoPath($rutaCompleta);
        
        $this->mensajeroRepository->save($mensajero, true);

        return $nombreArchivo;
    }

    /**
     * Elimina la foto del documento.
     * Solo permite eliminar si el mensajero no ha sido aprobado.
     * 
     * @throws \RuntimeException Si el mensajero ya fue aprobado
     */
    public function eliminarFotoDocumento(Mensajero $mensajero): void
    {
        if ($mensajero->getEstadoAprobacion() === EstadoAprobacion::APROBADO) {
            throw new \RuntimeException('No se puede eliminar la foto del documento una vez aprobado el mensajero.');
        }

        $mensajero->setFotoDocumentoPath(null);
        $this->mensajeroRepository->save($mensajero, true);
    }

    /**
     * Registra un heartbeat del mensajero y actualiza su disponibilidad.
     * El heartbeat marca al mensajero como activo y disponible (si está aprobado y no bloqueado).
     */
    public function registrarHeartbeat(Mensajero $mensajero): void
    {
        $mensajero->registrarHeartbeat();
        $mensajero->actualizarDisponibilidadPorHeartbeat();
        $this->mensajeroRepository->save($mensajero, true);
    }

    /**
     * Establece manualmente la disponibilidad del mensajero.
     * Nota: La disponibilidad real depende del heartbeat reciente.
     */
    public function establecerDisponibilidad(Mensajero $mensajero, bool $disponible): void
    {
        // Solo puede establecerse como disponible si está activo (heartbeat reciente)
        if ($disponible && !$mensajero->esActivo()) {
            throw new \RuntimeException('El mensajero debe enviar un heartbeat reciente para estar disponible.');
        }

        $mensajero->setDisponible($disponible);
        $this->mensajeroRepository->save($mensajero, true);
    }

    /**
     * Obtiene todos los mensajeros disponibles y activos (con heartbeat reciente).
     */
    public function obtenerMensajerosDisponibles(int $limite = 20): array
    {
        return $this->mensajeroRepository->findDisponiblesYaprobados($limite);
    }

    /**
     * Obtiene mensajeros por estado de aprobación.
     */
    public function obtenerMensajerosPorEstado(EstadoAprobacion $estado, int $limite = 50): array
    {
        return $this->mensajeroRepository->findByEstadoAprobacion($estado, $limite);
    }

    /**
     * Obtiene un mensajero por su ID de usuario.
     */
    public function obtenerPorUsuarioId(int $usuarioId): ?Mensajero
    {
        return $this->mensajeroRepository->findOneByUsuarioId($usuarioId);
    }

    /**
     * Aprueba un mensajero.
     */
    public function aprobarMensajero(Mensajero $mensajero): void
    {
        $mensajero->setEstadoAprobacion(EstadoAprobacion::APROBADO);
        $mensajero->resetearRechazos();
        $this->mensajeroRepository->save($mensajero, true);
    }

    /**
     * Rechaza un mensajero.
     */
    public function rechazarMensajero(Mensajero $mensajero): void
    {
        $mensajero->setEstadoAprobacion(EstadoAprobacion::RECHAZADO);
        $this->mensajeroRepository->save($mensajero, true);
    }

    /**
     * Verifica y actualiza la disponibilidad de todos los mensajeros basándose en sus heartbeats.
     * Este método debería ejecutarse periódicamente (ej. cada minuto).
     */
    public function actualizarDisponibilidadGlobal(): int
    {
        $mensajeros = $this->mensajeroRepository->findAll();
        $actualizados = 0;

        foreach ($mensajeros as $mensajero) {
            $disponibleAntes = $mensajero->isDisponible();
            $mensajero->actualizarDisponibilidadPorHeartbeat();
            
            if ($mensajero->isDisponible() !== $disponibleAntes) {
                $actualizados++;
            }
        }

        if ($actualizados > 0) {
            $this->entityManager->flush();
        }

        return $actualizados;
    }

    /**
     * Cuenta mensajeros por estado de aprobación.
     */
    public function contarPorEstado(EstadoAprobacion $estado): int
    {
        return $this->mensajeroRepository->countByEstadoAprobacion($estado);
    }

    /**
     * Incrementa los rechazos consecutivos y aplica bloqueo si corresponde.
     */
    public function incrementarRechazos(Mensajero $mensajero): void
    {
        $mensajero->incrementarRechazo();
        $this->mensajeroRepository->save($mensajero, true);
    }

    /**
     * Resetea los rechazos consecutivos después de una aceptación exitosa.
     */
    public function resetearRechazos(Mensajero $mensajero): void
    {
        $mensajero->resetearRechazos();
        $this->mensajeroRepository->save($mensajero, true);
    }

    /**
     * Actualiza la calificación promedio del mensajero.
     */
    public function actualizarCalificacionPromedio(Mensajero $mensajero, float $nuevaCalificacion): void
    {
        $totalEnvios = $mensajero->getTotalEnvios();
        $calificacionActual = (float) $mensajero->getCalificacionPromedio();

        if ($totalEnvios === 0) {
            $promedio = $nuevaCalificacion;
        } else {
            $promedio = (($calificacionActual * $totalEnvios) + $nuevaCalificacion) / ($totalEnvios + 1);
        }

        $mensajero->setCalificacionPromedio(number_format($promedio, 2, '.', ''));
        $this->mensajeroRepository->save($mensajero, true);
    }

    /**
     * Incrementa el total de envíos completados.
     */
    public function incrementarTotalEnvios(Mensajero $mensajero): void
    {
        $mensajero->incrementarTotalEnvios();
        $this->mensajeroRepository->save($mensajero, true);
    }
}
