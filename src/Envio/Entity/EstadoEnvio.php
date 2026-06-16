<?php

declare(strict_types=1);

namespace App\Envio\Entity;

enum EstadoEnvio: string
{
    case PENDIENTE = 'pendiente';
    case PAGADO = 'pagado';
    case ASIGNADO = 'asignado';
    case RECOGIDO = 'recogido';
    case EN_CAMINO = 'en_camino';
    case ENTREGADO = 'entregado';
    case CANCELADO = 'cancelado';
}
