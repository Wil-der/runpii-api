<?php

declare(strict_types=1);

namespace App\Envio\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Mensaje para reasignar un envío a un nuevo mensajero.
 * Se utiliza cuando el mensajero actual no puede completar el envío
 * o cuando se necesita cambiar la asignación por razones operativas.
 */
class ReasignarEnvioMessage
{
    public function __construct(
        public readonly Uuid $envioUuid,
        public readonly ?Uuid $nuevoMensajeroUuid = null,
        public readonly string $motivo = 'reasignacion_operativa'
    ) {
    }
}
