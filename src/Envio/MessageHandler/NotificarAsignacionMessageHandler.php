<?php

declare(strict_types=1);

namespace App\Envio\MessageHandler;

use App\Envio\Message\NotificarAsignacionMessage;
use App\Envio\Repository\EnvioRepository;
use App\Mensajero\Repository\MensajeroRepository;
use App\Shared\Message\EnviarNotificacionMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class NotificarAsignacionMessageHandler
{
    public function __construct(
        private readonly EnvioRepository $envioRepository,
        private readonly MensajeroRepository $mensajeroRepository,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @throws UnrecoverableMessageHandlingException Si el envío o mensajero no existen
     */
    public function __invoke(NotificarAsignacionMessage $message): void
    {
        $envio = $this->envioRepository->findOneByUuid($message->envioUuid->toRfc4122());

        if (!$envio) {
            throw new UnrecoverableMessageHandlingException(
                sprintf('No se encontró el envío con UUID: %s', $message->envioUuid->toRfc4122())
            );
        }

        $mensajero = $this->mensajeroRepository->findOneByUuid($message->mensajeroUuid->toRfc4122());

        if (!$mensajero) {
            throw new UnrecoverableMessageHandlingException(
                sprintf('No se encontró el mensajero con UUID: %s', $message->mensajeroUuid->toRfc4122())
            );
        }

        // Enviar notificación push al mensajero
        $titulo = $message->esReasignacion ? '🔄 Nueva asignación (Reasignación)' : '📦 Nuevo envío asignado';
        $mensaje = sprintf(
            'Se te ha asignado un nuevo envío. Origen: %s, Destino: %s',
            $envio->getOrigenDireccion(),
            $envio->getDestinoDireccion()
        );

        $this->bus->dispatch(new EnviarNotificacionMessage(
            destinatarioId: $mensajero->getUsuario()->getUuid(),
            tipoDestinatario: 'mensajero',
            titulo: $titulo,
            mensaje: $mensaje,
            tipo: EnviarNotificacionMessage::TIPO_PUSH,
            datosAdicionales: [
                'tipo' => 'asignacion_envio',
                'envio_uuid' => $envio->getUuidString(),
                'es_reasignacion' => $message->esReasignacion,
            ]
        ));
    }
}
