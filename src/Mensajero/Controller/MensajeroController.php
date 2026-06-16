<?php

declare(strict_types=1);

namespace App\Mensajero\Controller;

use App\Mensajero\Service\MensajeroService;
use App\Mensajero\Repository\MensajeroRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ForbiddenHttpException;

#[Route('/api/v1/mensajero')]
class MensajeroController extends AbstractController
{
    public function __construct(
        private readonly MensajeroService $mensajeroService,
        private readonly MensajeroRepository $mensajeroRepository
    ) {
    }

    /**
     * Obtiene el perfil del mensajero actual.
     */
    #[Route('/perfil', methods: ['GET'])]
    public function obtenerPerfil(): JsonResponse
    {
        $usuario = $this->getUser();
        
        if (!$usuario) {
            throw new NotFoundHttpException('Usuario no autenticado');
        }

        $mensajero = $this->mensajeroRepository->findOneByUsuarioId($usuario->getId());

        if (!$mensajero) {
            throw new NotFoundHttpException('No se encontró perfil de mensajero');
        }

        return $this->json([
            'id' => $mensajero->getId(),
            'usuario' => [
                'id' => $mensajero->getUsuario()->getId(),
                'nombre' => $mensajero->getUsuario()->getNombre(),
                'email' => $mensajero->getUsuario()->getEmail(),
                'telefono' => $mensajero->getUsuario()->getTelefono(),
            ],
            'tipoVehiculo' => $mensajero->getTipoVehiculo(),
            'estadoAprobacion' => $mensajero->getEstadoAprobacion()->value,
            'disponible' => $mensajero->isDisponible(),
            'calificacionPromedio' => $mensajero->getCalificacionPromedio(),
            'totalEnvios' => $mensajero->getTotalEnvios(),
            'bloqueadoHasta' => $mensajero->getBloqueadoHasta()?->format(\DateTimeInterface::ATOM),
            'ultimoHeartbeat' => $mensajero->getUltimoHeartbeat()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Actualiza la foto del documento (CI) del mensajero.
     */
    #[Route('/perfil/foto-documento', methods: ['POST'])]
    public function actualizarFotoDocumento(Request $request): JsonResponse
    {
        $usuario = $this->getUser();
        
        if (!$usuario) {
            throw new NotFoundHttpException('Usuario no autenticado');
        }

        $mensajero = $this->mensajeroRepository->findOneByUsuarioId($usuario->getId());

        if (!$mensajero) {
            throw new NotFoundHttpException('No se encontró perfil de mensajero');
        }

        $foto = $request->files->get('foto_documento');

        if (!$foto) {
            throw new BadRequestHttpException('No se proporcionó ninguna foto');
        }

        // Validar que sea una imagen
        if (!$foto->isValid() || !in_array($foto->getMimeType(), ['image/jpeg', 'image/png', 'image/jpg'])) {
            throw new BadRequestHttpException('La foto debe ser una imagen válida (JPEG o PNG)');
        }

        // Validar tamaño máximo (5MB)
        if ($foto->getSize() > 5 * 1024 * 1024) {
            throw new BadRequestHttpException('La foto no puede superar los 5MB');
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/uploads/documentos';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        try {
            $nombreArchivo = $this->mensajeroService->actualizarFotoDocumento($mensajero, $foto, $uploadDir);
            
            return $this->json([
                'mensaje' => 'Foto actualizada correctamente',
                'archivo' => $nombreArchivo,
            ]);
        } catch (\RuntimeException $e) {
            throw new ForbiddenHttpException($e->getMessage());
        }
    }

    /**
     * Elimina la foto del documento.
     */
    #[Route('/perfil/foto-documento', methods: ['DELETE'])]
    public function eliminarFotoDocumento(): JsonResponse
    {
        $usuario = $this->getUser();
        
        if (!$usuario) {
            throw new NotFoundHttpException('Usuario no autenticado');
        }

        $mensajero = $this->mensajeroRepository->findOneByUsuarioId($usuario->getId());

        if (!$mensajero) {
            throw new NotFoundHttpException('No se encontró perfil de mensajero');
        }

        try {
            $this->mensajeroService->eliminarFotoDocumento($mensajero);
            
            return $this->json([
                'mensaje' => 'Foto eliminada correctamente',
            ]);
        } catch (\RuntimeException $e) {
            throw new ForbiddenHttpException($e->getMessage());
        }
    }

    /**
     * Registra un heartbeat del mensajero.
     * El heartbeat mantiene al mensajero activo y disponible.
     */
    #[Route('/heartbeat', methods: ['POST'])]
    public function registrarHeartbeat(Request $request): JsonResponse
    {
        $usuario = $this->getUser();
        
        if (!$usuario) {
            throw new NotFoundHttpException('Usuario no autenticado');
        }

        $mensajero = $this->mensajeroRepository->findOneByUsuarioId($usuario->getId());

        if (!$mensajero) {
            throw new NotFoundHttpException('No se encontró perfil de mensajero');
        }

        // Verificar que el mensajero esté aprobado
        if ($mensajero->getEstadoAprobacion() !== \App\Mensajero\Entity\EstadoAprobacion::APROBADO) {
            throw new ForbiddenHttpException('Solo los mensajeros aprobados pueden enviar heartbeat');
        }

        // Opcional: recibir lat/lng para actualizar ubicación en Redis
        $contenido = json_decode($request->getContent(), true);
        $lat = $contenido['lat'] ?? null;
        $lng = $contenido['lng'] ?? null;

        // Registrar heartbeat
        $this->mensajeroService->registrarHeartbeat($mensajero);

        // Si se proporcionan coordenadas, actualizar ubicación en Redis
        // (Esto se manejaría en el servicio de Tracking)
        
        return $this->json([
            'mensaje' => 'Heartbeat registrado',
            'disponible' => $mensajero->isDisponible(),
            'activo' => $mensajero->esActivo(),
            'ultimoHeartbeat' => $mensajero->getUltimoHeartbeat()->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Establece manualmente la disponibilidad del mensajero.
     */
    #[Route('/disponibilidad', methods: ['POST'])]
    public function establecerDisponibilidad(Request $request): JsonResponse
    {
        $usuario = $this->getUser();
        
        if (!$usuario) {
            throw new NotFoundHttpException('Usuario no autenticado');
        }

        $mensajero = $this->mensajeroRepository->findOneByUsuarioId($usuario->getId());

        if (!$mensajero) {
            throw new NotFoundHttpException('No se encontró perfil de mensajero');
        }

        $contenido = json_decode($request->getContent(), true);
        $disponible = $contenido['disponible'] ?? null;

        if ($disponible === null) {
            throw new BadRequestHttpException('El campo "disponible" es requerido');
        }

        if (!is_bool($disponible)) {
            throw new BadRequestHttpException('El campo "disponible" debe ser un booleano');
        }

        try {
            $this->mensajeroService->establecerDisponibilidad($mensajero, $disponible);
            
            return $this->json([
                'mensaje' => 'Disponibilidad actualizada',
                'disponible' => $mensajero->isDisponible(),
            ]);
        } catch (\RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * Obtiene el estado actual del mensajero (disponibilidad, bloqueos, etc.).
     */
    #[Route('/estado', methods: ['GET'])]
    public function obtenerEstado(): JsonResponse
    {
        $usuario = $this->getUser();
        
        if (!$usuario) {
            throw new NotFoundHttpException('Usuario no autenticado');
        }

        $mensajero = $this->mensajeroRepository->findOneByUsuarioId($usuario->getId());

        if (!$mensajero) {
            throw new NotFoundHttpException('No se encontró perfil de mensajero');
        }

        return $this->json([
            'estadoAprobacion' => $mensajero->getEstadoAprobacion()->value,
            'disponible' => $mensajero->isDisponible(),
            'activo' => $mensajero->esActivo(),
            'bloqueadoHasta' => $mensajero->getBloqueadoHasta()?->format(\DateTimeInterface::ATOM),
            'rechazosConsecutivos' => $mensajero->getRechazosConsecutivos(),
            'calificacionPromedio' => $mensajero->getCalificacionPromedio(),
            'totalEnvios' => $mensajero->getTotalEnvios(),
            'ultimoHeartbeat' => $mensajero->getUltimoHeartbeat()?->format(\DateTimeInterface::ATOM),
        ]);
    }
}
