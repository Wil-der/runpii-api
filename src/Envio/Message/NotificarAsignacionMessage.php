<?php

declare(strict_types=1);

namespace App\Envio\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Mensaje para notificar a un mensajero sobre una nueva asignación de envío.
 * Se envía cuando un envío es asignado a un mensajero por primera vez.
 */
class NotificarAsignacionMessage
{
    public function __construct(
        public readonly Uuid $envioUuid,
        public readonly Uuid $mensajeroUuid,
        public readonly bool $esReasignacion = false
    ) {
    }
}
