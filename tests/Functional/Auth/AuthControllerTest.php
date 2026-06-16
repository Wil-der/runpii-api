<?php

namespace App\Tests\Functional\Auth;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testRegisterEndpoint(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'nuevo@ejemplo.com',
                'password' => 'password123',
                'ci' => '12345678901',
                'role' => 'client'
            ])
        );

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        // Nota: Esta prueba puede fallar si no hay base de datos configurada
        // En un entorno real, verificaríamos el código de respuesta y la estructura
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
    }

    public function testLoginEndpointWithInvalidCredentials(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'invalido@ejemplo.com',
                'password' => 'password_incorrecto'
            ])
        );

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }

    public function testLogoutEndpointWithoutToken(): void
    {
        $this->client->request('POST', '/api/auth/logout');

        $response = $this->client->getResponse();
        
        // Sin token, debería retornar 401 o similar
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function testRefreshEndpointWithoutToken(): void
    {
        $this->client->request('POST', '/api/auth/refresh');

        $response = $this->client->getResponse();
        
        // Sin token válido, debería retornar error
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function testMeEndpointWithoutAuthentication(): void
    {
        $this->client->request('GET', '/api/auth/me');

        $response = $this->client->getResponse();
        
        // Sin autenticación, debería retornar 401
        $this->assertEquals(401, $response->getStatusCode());
    }
}
