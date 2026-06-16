<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\Usuario;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TokenBlacklistService
{
    private \Redis $redis;
    private int $ttl;

    /**
     * @param \Redis $redis Conexión a Redis
     * @param int $ttl Tiempo de vida del token en segundos (debe coincidir con el TTL del JWT)
     */
    public function __construct(\Redis $redis, int $ttl = 3600)
    {
        $this->redis = $redis;
        $this->ttl = $ttl;
    }

    /**
     * Añade un token a la blacklist
     * 
     * @param string $token El token JWT a invalidar
     * @return bool True si se añadió correctamente
     */
    public function addToBlacklist(string $token): bool
    {
        return $this->redis->setex(
            "blacklist:$token",
            $this->ttl,
            'invalidated'
        );
    }

    /**
     * Verifica si un token está en la blacklist
     * 
     * @param string $token El token JWT a verificar
     * @return bool True si el token está en la blacklist
     */
    public function isBlacklisted(string $token): bool
    {
        return $this->redis->exists("blacklist:$token") === 1;
    }

    /**
     * Invalida todos los tokens de un usuario (logout global)
     * 
     * @param Usuario $usuario El usuario cuyos tokens se invalidarán
     * @param string $tokenId Identificador único del token actual (si se proporciona en el JWT)
     */
    public function invalidateUserTokens(Usuario $usuario, ?string $tokenId = null): void
    {
        // Si se proporciona un tokenId específico, lo añadimos a la blacklist
        if ($tokenId) {
            $this->addToBlacklist($tokenId);
        }

        // Guardamos un timestamp para invalidar todos los tokens anteriores a esta fecha
        $this->redis->setex(
            "user_token_invalidation:{$usuario->getId()}",
            $this->ttl * 24, // 24 horas como máximo
            time()
        );
    }

    /**
     * Verifica si todos los tokens de un usuario han sido invalidados
     * 
     * @param Usuario $usuario El usuario a verificar
     * @param int $tokenIssuedAt Timestamp de cuando se emitió el token (iat claim)
     * @return bool True si los tokens del usuario están invalidados
     */
    public function areUserTokensInvalidated(Usuario $usuario, int $tokenIssuedAt): bool
    {
        $invalidationTime = $this->redis->get("user_token_invalidation:{$usuario->getId()}");
        
        if ($invalidationTime && (int)$invalidationTime < $tokenIssuedAt) {
            return false; // El token fue emitido después de la invalidación
        }

        return $invalidationTime !== false;
    }
}
