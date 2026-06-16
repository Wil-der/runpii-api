<?php

declare(strict_types=1);

namespace App\Envio\Entity;

enum TipoFotoEnvio: string
{
    case RECOGIDA = 'recogida';
    case ENTREGA = 'entrega';
    case COMPROBANTE_PAGO = 'comprobante_pago';
}
