<?php

declare(strict_types=1);

namespace App\Chat\Entity;

enum TipoMensaje: string
{
    case TEXTO = 'texto';
    case FOTO = 'foto';
    case SISTEMA = 'sistema';
}
