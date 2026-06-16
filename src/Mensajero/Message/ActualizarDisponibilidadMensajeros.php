<?php

declare(strict_types=1);

namespace App\Mensajero\Message;

/**
 * Mensaje para actualizar la disponibilidad de todos los mensajeros basado en sus heartbeats.
 * Se envía periódicamente (cada minuto) a través de Symfony Messenger.
 */
class ActualizarDisponibilidadMensajeros
{
    public function __construct(
        public readonly int $umbralSegundos = 120
    ) {
    }
}
