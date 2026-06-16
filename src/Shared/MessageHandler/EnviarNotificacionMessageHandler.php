<?php

declare(strict_types=1);

namespace App\Shared\MessageHandler;

use App\Shared\Message\EnviarNotificacionMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class EnviarNotificacionMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Procesa el envío de una notificación.
     * 
     * En producción, este handler debería integrar con:
     * - Firebase Cloud Messaging para notificaciones push
     * - Twilio o similar para SMS
     * - SendGrid/Mailgun para emails
     * 
     * Por ahora, solo loguea la notificación para debugging.
     */
    public function __invoke(EnviarNotificacionMessage $message): void
    {
        $this->logger->info('Enviando notificación', [
            'destinatario_id' => $message->destinatarioId->toRfc4122(),
            'tipo_destinatario' => $message->tipoDestinatario,
            'titulo' => $message->titulo,
            'mensaje' => $message->mensaje,
            'tipo' => $message->tipo,
            'datos_adicionales' => $message->datosAdicionales,
        ]);

        // TODO: Implementar integración con servicios de notificación reales
        // Ejemplo para Firebase Cloud Messaging:
        // $this->firebaseMessaging->send([
        //     'to' => $deviceToken,
        //     'notification' => [
        //         'title' => $message->titulo,
        //         'body' => $message->mensaje,
        //         'data' => $message->datosAdicionales,
        //     ],
        // ]);
    }
}
