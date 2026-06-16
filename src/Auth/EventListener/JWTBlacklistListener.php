<?php

declare(strict_types=1);

namespace App\Auth\EventListener;

use App\Auth\Service\TokenBlacklistService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class JWTBlacklistListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenBlacklistService $tokenBlacklistService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_DECODED => 'onJWTDecoded',
        ];
    }

    /**
     * Verifica si el token JWT está en la blacklist antes de permitir su uso
     */
    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $jwt = $event->getToken();

        // Verificar si el token está en la blacklist
        if ($this->tokenBlacklistService->isBlacklisted($jwt)) {
            throw new UnauthorizedHttpException('Token inválido o expirado');
        }

        // Si hay un user_id en el payload, verificar si los tokens del usuario fueron invalidados
        if (isset($payload['user_id']) && isset($payload['iat'])) {
            // Aquí se podría cargar el usuario y verificar invalidación global
            // Esto requiere acceso al EntityManager o repositorio
            // Por simplicidad, esta verificación se puede implementar más adelante
        }
    }
}
