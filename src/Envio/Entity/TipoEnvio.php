<?php

declare(strict_types=1);

namespace App\Envio\Entity;

enum TipoEnvio: string
{
    case INMEDIATO = 'inmediato';
    case PLANIFICADO = 'planificado';
}
