<?php

declare(strict_types=1);

namespace App\Tracking\MessageHandler;

use App\Tracking\Message\FlushUbicacionesMessage;
use App\Tracking\Entity\UbicacionMensajero;
use App\Mensajero\Repository\MensajeroRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class FlushUbicacionesMessageHandler
{
    public function __construct(
        private readonly MensajeroRepository $mensajeroRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @throws UnrecoverableMessageHandlingException Si algún mensajero no existe
     */
    public function __invoke(FlushUbicacionesMessage $message): void
    {
        foreach ($message->ubicaciones as $ubicacionData) {
            $mensajero = $this->mensajeroRepository->findOneByUuid($ubicacionData['mensajeroUuid']->toRfc4122());

            if (!$mensajero) {
                throw new UnrecoverableMessageHandlingException(
                    sprintf('No se encontró el mensajero con UUID: %s', $ubicacionData['mensajeroUuid']->toRfc4122())
                );
            }

            $ubicacion = new UbicacionMensajero();
            $ubicacion->setMensajero($mensajero);
            $ubicacion->setLat($ubicacionData['lat']);
            $ubicacion->setLng($ubicacionData['lng']);
            
            if (isset($ubicacionData['precisionM'])) {
                $ubicacion->setPrecisionM($ubicacionData['precisionM']);
            }

            $this->entityManager->persist($ubicacion);
        }

        $this->entityManager->flush();
    }
}
