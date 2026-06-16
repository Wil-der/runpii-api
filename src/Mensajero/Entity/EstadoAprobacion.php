<?php

declare(strict_types=1);

namespace App\Mensajero\Entity;

enum EstadoAprobacion: string
{
    case PENDIENTE = 'pendiente';
    case APROBADO = 'aprobado';
    case RECHAZADO = 'rechazado';
}
