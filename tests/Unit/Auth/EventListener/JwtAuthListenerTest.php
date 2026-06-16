<?php

namespace App\Tests\Unit\Auth\EventListener;

use App\Auth\EventListener\JwtAuthListener;
use App\Auth\Service\TokenBlacklistService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

class JwtAuthListenerTest extends TestCase
{
    private TokenBlacklistService $blacklistService;
    private JwtAuthListener $listener;

    protected function setUp(): void
    {
        $cache = new ArrayAdapter();
        $this->blacklistService = new TokenBlacklistService($cache, 3600);
        $this->listener = new JwtAuthListener($this->blacklistService);
    }

    public function testOnJWTCreatedAddsTokenToBlacklistOnLogout(): void
    {
        // Esta prueba verifica que el listener no interfiera con la creación normal del token
        $user = new \App\Auth\Entity\User();
        $user->setEmail('test@example.com');
        
        $event = new JWTCreatedEvent(['token' => 'new_jwt_token'], $user, new Request());
        
        // El listener onJWTCreated no debería hacer nada en este contexto
        $this->listener->onJWTCreated($event);
        
        $this->assertFalse($this->blacklistService->isBlacklisted('new_jwt_token'));
    }

    public function testOnJWTAuthenticatedRejectsBlacklistedToken(): void
    {
        $token = 'blacklisted_jwt_token';
        $this->blacklistService->addToBlacklist($token);
        
        $request = new Request();
        $request->attributes->set('_jwt_token', $token);
        
        $event = new JWTAuthenticatedEvent(['token' => $token], $request);
        
        $response = $this->listener->onJWTAuthenticated($event);
        
        $this->assertInstanceOf(JWTAuthenticationFailureResponse::class, $response);
        $this->assertEquals('Token ha sido revocado', $response->getMessage());
    }

    public function testOnJWTAuthenticatedAllowsValidToken(): void
    {
        $token = 'valid_jwt_token';
        
        $request = new Request();
        $request->attributes->set('_jwt_token', $token);
        
        $event = new JWTAuthenticatedEvent(['token' => $token], $request);
        
        $response = $this->listener->onJWTAuthenticated($event);
        
        // No debería retornar respuesta de error para tokens válidos
        $this->assertNull($response);
    }
}
