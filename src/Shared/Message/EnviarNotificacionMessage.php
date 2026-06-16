<?php

declare(strict_types=1);

namespace App\Shared\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Mensaje para enviar una notificación a un usuario o mensajero.
 * Se utiliza para notificaciones push, SMS o email.
 */
class EnviarNotificacionMessage
{
    public const TIPO_PUSH = 'push';
    public const TIPO_SMS = 'sms';
    public const TIPO_EMAIL = 'email';

    /**
     * @param Uuid $destinatarioId UUID del destinatario (usuario o mensajero)
     * @param string $tipoDestinatario 'usuario' o 'mensajero'
     * @param string $titulo Título de la notificación
     * @param string $mensaje Contenido de la notificación
     * @param string $tipo Tipo de notificación (push, sms, email)
     * @param array<string, mixed> $datosAdicionales Datos adicionales para la notificación
     */
    public function __construct(
        public readonly Uuid $destinatarioId,
        public readonly string $tipoDestinatario,
        public readonly string $titulo,
        public readonly string $mensaje,
        public readonly string $tipo = self::TIPO_PUSH,
        public readonly array $datosAdicionales = []
    ) {
    }
}
