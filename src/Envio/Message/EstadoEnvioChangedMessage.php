<?php

declare(strict_types=1);

namespace App\Envio\Message;

use Symfony\Component\Uid\Uuid;
use App\Envio\Entity\EstadoEnvio;

/**
 * Mensaje para notificar un cambio en el estado de un envío.
 * Se envía cuando el estado de un envío cambia para actualizar sistemas externos o notificar a los interesados.
 */
class EstadoEnvioChangedMessage
{
    /**
     * @param Uuid $envioUuid UUID del envío cuyo estado cambió
     * @param EstadoEnvio $estadoAnterior Estado anterior del envío
     * @param EstadoEnvio $estadoNuevo Nuevo estado del envío
     * @param \DateTimeInterface $fechaCambio Fecha y hora del cambio de estado
     * @param string|null $motivo Motivo del cambio (opcional, útil para cancelaciones)
     */
    public function __construct(
        public readonly Uuid $envioUuid,
        public readonly EstadoEnvio $estadoAnterior,
        public readonly EstadoEnvio $estadoNuevo,
        public readonly \DateTimeInterface $fechaCambio,
        public readonly ?string $motivo = null
    ) {
    }
}
