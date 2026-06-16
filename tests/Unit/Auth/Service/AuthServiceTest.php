<?php

namespace App\Tests\Unit\Auth\Service;

use App\Auth\Service\AuthService;
use App\Auth\Service\TokenBlacklistService;
use App\Auth\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    private AuthService $authService;
    private $userRepository;
    private $passwordHasher;
    private $jwtManager;
    private TokenBlacklistService $blacklistService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        
        $cache = new ArrayAdapter();
        $this->blacklistService = new TokenBlacklistService($cache, 3600);
        
        $this->authService = new AuthService(
            $this->userRepository,
            $this->passwordHasher,
            $this->jwtManager,
            $this->blacklistService
        );
    }

    public function testRegisterSuccess(): void
    {
        $email = 'test@example.com';
        $password = 'password123';
        $ci = '12345678901';
        
        $existingUser = null;
        $this->userRepository->method('findOneBy')->willReturn($existingUser);
        
        $hashedPassword = 'hashed_password';
        $this->passwordHasher->method('hashPassword')->willReturn($hashedPassword);
        
        $user = new \App\Auth\Entity\User();
        $user->setEmail($email);
        $user->setPassword($hashedPassword);
        $user->setCi($ci);
        $user->setRoles(['ROLE_CLIENT']);
        
        $this->userRepository->expects($this->once())->method('save');
        
        $result = $this->authService->register($email, $password, $ci, 'client');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user', $result);
    }

    public function testRegisterUserAlreadyExists(): void
    {
        $existingUser = new \App\Auth\Entity\User();
        $this->userRepository->method('findOneBy')->willReturn($existingUser);
        
        $result = $this->authService->register('test@example.com', 'password123', '12345678901', 'client');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertEquals('El usuario ya existe', $result['message']);
    }

    public function testLoginSuccess(): void
    {
        $user = new \App\Auth\Entity\User();
        $user->setEmail('test@example.com');
        $user->setPassword('hashed_password');
        $user->setRoles(['ROLE_CLIENT']);
        
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('verifyPassword')->willReturn(true);
        $this->jwtManager->method('create')->willReturn('jwt_token_here');
        
        $result = $this->authService->login('test@example.com', 'password123');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('jwt_token_here', $result['token']);
    }

    public function testLoginInvalidCredentials(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        
        $result = $this->authService->login('nonexistent@example.com', 'password123');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertEquals('Credenciales inválidas', $result['message']);
    }

    public function testLogout(): void
    {
        $token = 'jwt_token_to_blacklist';
        
        $result = $this->authService->logout($token);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertTrue($this->blacklistService->isBlacklisted($token));
    }
}
