<?php

namespace Abfadel\BoxAdapter\Tests;

use Abfadel\BoxAdapter\Api\BoxJwtAuth;
use PHPUnit\Framework\TestCase;

class BoxJwtAuthTest extends TestCase
{
    protected function getTestConfig(): array
    {
        return [
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'enterprise_id' => 'test_enterprise_id',
            'private_key' => $this->generateTestPrivateKey(),
            'private_key_password' => '',
            'key_id' => 'test_key_id',
            'auth_url' => 'https://api.box.com/oauth2/token',
            'token_ttl' => 60,
        ];
    }

    protected function generateTestPrivateKey(): string
    {
        // Generate a test RSA private key
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privateKey);
        
        return $privateKey;
    }

    public function test_jwt_auth_can_be_instantiated()
    {
        $config = $this->getTestConfig();
        $auth = new BoxJwtAuth($config);
        
        $this->assertInstanceOf(BoxJwtAuth::class, $auth);
    }

    public function test_jwt_auth_loads_private_key()
    {
        $config = $this->getTestConfig();
        $auth = new BoxJwtAuth($config);
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($auth);
        $method = $reflection->getMethod('loadPrivateKey');
        $method->setAccessible(true);
        
        $privateKey = $method->invoke($auth);
        
        $this->assertNotEmpty($privateKey);
    }

    public function test_jwt_auth_generates_assertion()
    {
        $config = $this->getTestConfig();
        $auth = new BoxJwtAuth($config);
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($auth);
        $method = $reflection->getMethod('generateJwtAssertion');
        $method->setAccessible(true);
        
        $assertion = $method->invoke($auth);
        
        $this->assertNotEmpty($assertion);
        $this->assertIsString($assertion);
        
        // JWT should have 3 parts separated by dots
        $parts = explode('.', $assertion);
        $this->assertCount(3, $parts);
    }

    public function test_jwt_auth_token_invalidation()
    {
        $config = $this->getTestConfig();
        $auth = new BoxJwtAuth($config);
        
        // Use reflection to set a token
        $reflection = new \ReflectionClass($auth);
        
        $tokenProp = $reflection->getProperty('accessToken');
        $tokenProp->setAccessible(true);
        $tokenProp->setValue($auth, 'test_token');
        
        $expiresProp = $reflection->getProperty('tokenExpiresAt');
        $expiresProp->setAccessible(true);
        $expiresProp->setValue($auth, time() + 3600);
        
        // Invalidate token
        $auth->invalidateToken();
        
        $this->assertNull($tokenProp->getValue($auth));
        $this->assertNull($expiresProp->getValue($auth));
    }
}
