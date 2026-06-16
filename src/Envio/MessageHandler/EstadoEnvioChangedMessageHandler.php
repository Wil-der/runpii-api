<?php

declare(strict_types=1);

namespace App\Envio\MessageHandler;

use App\Envio\Message\EstadoEnvioChangedMessage;
use App\Shared\Message\EnviarNotificacionMessage;
use App\Envio\Repository\EnvioRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class EstadoEnvioChangedMessageHandler
{
    public function __construct(
        private readonly EnvioRepository $envioRepository,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws UnrecoverableMessageHandlingException Si el envío no existe
     */
    public function __invoke(EstadoEnvioChangedMessage $message): void
    {
        $envio = $this->envioRepository->findOneByUuid($message->envioUuid->toRfc4122());

        if (!$envio) {
            throw new UnrecoverableMessageHandlingException(
                sprintf('No se encontró el envío con UUID: %s', $message->envioUuid->toRfc4122())
            );
        }

        $this->logger->info('Cambio de estado de envío procesado', [
            'envio_uuid' => $message->envioUuid->toRfc4122(),
            'estado_anterior' => $message->estadoAnterior->value,
            'estado_nuevo' => $message->estadoNuevo->value,
            'motivo' => $message->motivo,
        ]);

        // Enviar notificaciones según el cambio de estado
        match ([$message->estadoAnterior->value, $message->estadoNuevo->value]) {
            // Cuando el envío es asignado, notificar al mensajero
            ['pendiente', 'asignado'],
            ['pagado', 'asignado'] => $this->notificarAlCliente($envio, 'Tu envío ha sido asignado a un mensajero'),
            
            // Cuando el envío es recogido
            ['asignado', 'recogido'] => $this->notificarAlCliente($envio, 'El mensajero ha recogido tu envío'),
            
            // Cuando el envío está en camino
            ['recogido', 'en_camino'] => $this->notificarAlCliente($envio, 'Tu envío está en camino al destino'),
            
            // Cuando el envío es entregado
            ['en_camino', 'entregado'] => $this->notificarAlCliente($envio, '¡Tu envío ha sido entregado exitosamente!'),
            
            // Cuando el envío es cancelado
            ['pendiente', 'cancelado'],
            ['asignado', 'cancelado'],
            ['recogido', 'cancelado'],
            ['en_camino', 'cancelado'] => $this->notificarAlCliente($envio, 'Tu envío ha sido cancelado. Motivo: ' . ($message->motivo ?? 'No especificado')),
            
            default => null,
        };
    }

    private function notificarAlCliente(object $envio, string $mensaje): void
    {
        $this->bus->dispatch(new EnviarNotificacionMessage(
            destinatarioId: $envio->getCliente()->getUuid(),
            tipoDestinatario: 'usuario',
            titulo: 'Actualización de tu envío',
            mensaje: $mensaje,
            tipo: EnviarNotificacionMessage::TIPO_PUSH,
            datosAdicionales: [
                'tipo' => 'cambio_estado_envio',
                'envio_uuid' => $envio->getUuidString(),
                'estado' => $envio->getEstado()->value,
            ]
        ));
    }
}
