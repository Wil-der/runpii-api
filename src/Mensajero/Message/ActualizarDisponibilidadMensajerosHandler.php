<?php

declare(strict_types=1);

namespace App\Mensajero\Message;

use App\Mensajero\Service\MensajeroService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ActualizarDisponibilidadMensajerosHandler
{
    public function __construct(
        private readonly MensajeroService $mensajeroService
    ) {
    }

    public function __invoke(ActualizarDisponibilidadMensajeros $message): void
    {
        $this->mensajeroService->actualizarDisponibilidadGlobal();
    }
}
