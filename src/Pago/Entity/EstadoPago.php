<?php

declare(strict_types=1);

namespace App\Pago\Entity;

enum EstadoPago: string
{
    case PENDIENTE = 'pendiente';
    case VERIFICADO = 'verificado';
    case RECHAZADO = 'rechazado';
}
