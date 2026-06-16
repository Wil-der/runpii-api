<?php

declare(strict_types=1);

namespace App\Tracking\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Mensaje para procesar un batch de ubicaciones de mensajeros.
 * Se utiliza para guardar múltiples ubicaciones de manera eficiente en la base de datos.
 */
class FlushUbicacionesMessage
{
    /**
     * @param array<int, array{mensajeroUuid: Uuid, lat: string, lng: string, precisionM?: int}> $ubicaciones
     */
    public function __construct(
        public readonly array $ubicaciones
    ) {
    }
}
