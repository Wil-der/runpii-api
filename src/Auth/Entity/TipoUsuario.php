<?php

declare(strict_types=1);

namespace App\Auth\Entity;

enum TipoUsuario: string
{
    case CLIENTE = 'cliente';
    case MENSAJERO = 'mensajero';
    case ADMIN = 'admin';
}
