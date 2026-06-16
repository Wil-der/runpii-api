<?php

namespace App\Tests\Unit\Auth\Service;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use App\Auth\Service\TokenBlacklistService;
use PHPUnit\Framework\TestCase;

class TokenBlacklistServiceTest extends TestCase
{
    private TokenBlacklistService $blacklistService;
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->blacklistService = new TokenBlacklistService($this->cache, 3600);
    }

    public function testAddToBlacklist(): void
    {
        $token = 'test_jwt_token';
        $result = $this->blacklistService->addToBlacklist($token);
        
        $this->assertTrue($result);
        $this->assertTrue($this->blacklistService->isBlacklisted($token));
    }

    public function testIsBlacklistedReturnsTrueForBlacklistedToken(): void
    {
        $token = 'another_test_token';
        $this->blacklistService->addToBlacklist($token);
        
        $this->assertTrue($this->blacklistService->isBlacklisted($token));
    }

    public function testIsBlacklistedReturnsFalseForValidToken(): void
    {
        $token = 'valid_token_not_blacklisted';
        
        $this->assertFalse($this->blacklistService->isBlacklisted($token));
    }

    public function testClearBlacklist(): void
    {
        $token1 = 'token_1';
        $token2 = 'token_2';
        
        $this->blacklistService->addToBlacklist($token1);
        $this->blacklistService->addToBlacklist($token2);
        
        $this->blacklistService->clearBlacklist();
        
        $this->assertFalse($this->blacklistService->isBlacklisted($token1));
        $this->assertFalse($this->blacklistService->isBlacklisted($token2));
    }
}
