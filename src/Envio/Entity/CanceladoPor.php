<?php

declare(strict_types=1);

namespace App\Envio\Entity;

enum CanceladoPor: string
{
    case CLIENTE = 'cliente';
    case ADMIN = 'admin';
    case SISTEMA = 'sistema';
}
