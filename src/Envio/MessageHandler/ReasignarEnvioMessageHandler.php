<?php

declare(strict_types=1);

namespace App\Envio\MessageHandler;

use App\Envio\Message\ReasignarEnvioMessage;
use App\Envio\Repository\EnvioRepository;
use App\Mensajero\Repository\MensajeroRepository;
use App\Envio\Entity\EstadoEnvio;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class ReasignarEnvioMessageHandler
{
    public function __construct(
        private readonly EnvioRepository $envioRepository,
        private readonly MensajeroRepository $mensajeroRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @throws UnrecoverableMessageHandlingException Si el envío no existe o no se puede reasignar
     */
    public function __invoke(ReasignarEnvioMessage $message): void
    {
        $envio = $this->envioRepository->findOneByUuid($message->envioUuid->toRfc4122());

        if (!$envio) {
            throw new UnrecoverableMessageHandlingException(
                sprintf('No se encontró el envío con UUID: %s', $message->envioUuid->toRfc4122())
            );
        }

        // Verificar que el envío esté asignado (solo se pueden reasignar envíos ya asignados)
        if ($envio->getEstado() !== EstadoEnvio::ASIGNADO && 
            $envio->getEstado() !== EstadoEnvio::RECOGIDO &&
            $envio->getEstado() !== EstadoEnvio::EN_CAMINO) {
            throw new UnrecoverableMessageHandlingException(
                sprintf('El envío %s no puede ser reasignado en estado: %s', 
                    $message->envioUuid->toRfc4122(), 
                    $envio->getEstado()->value
                )
            );
        }

        // Si se proporciona un nuevo mensajero, verificar que exista y esté disponible
        $nuevoMensajero = null;
        if ($message->nuevoMensajeroUuid !== null) {
            $nuevoMensajero = $this->mensajeroRepository->findOneByUuid($message->nuevoMensajeroUuid->toRfc4122());
            
            if (!$nuevoMensajero) {
                throw new UnrecoverableMessageHandlingException(
                    sprintf('No se encontró el mensajero con UUID: %s', $message->nuevoMensajeroUuid->toRfc4122())
                );
            }

            // Asignar el nuevo mensajero al envío
            $envio->setMensajero($nuevoMensajero);
        } else {
            // Si no se proporciona un mensajero específico, liberar el envío (quitar mensajero actual)
            $envio->setMensajero(null);
            $envio->setEstado(EstadoEnvio::PENDIENTE);
        }

        $this->entityManager->flush();
    }
}
