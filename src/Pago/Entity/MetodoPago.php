<?php

declare(strict_types=1);

namespace App\Pago\Entity;

enum MetodoPago: string
{
    case TRANSFERMOVIL = 'transfermovil';
    case ENZONA = 'enzona';
}
