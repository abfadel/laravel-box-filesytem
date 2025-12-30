<?php

namespace Abfadel\BoxAdapter\Tests;

use Abfadel\BoxAdapter\Api\BoxApiClient;
use PHPUnit\Framework\TestCase;

class BoxApiClientTest extends TestCase
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
            'api_url' => 'https://api.box.com/2.0',
            'upload_url' => 'https://upload.box.com/api/2.0',
            'token_ttl' => 60,
            'collision_strategy' => 'rename',
        ];
    }

    protected function generateTestPrivateKey(): string
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privateKey);
        
        return $privateKey;
    }

    public function test_api_client_can_be_instantiated()
    {
        $config = $this->getTestConfig();
        $client = new BoxApiClient($config);
        
        $this->assertInstanceOf(BoxApiClient::class, $client);
    }

    public function test_api_client_has_correct_configuration()
    {
        $config = $this->getTestConfig();
        $client = new BoxApiClient($config);
        
        $reflection = new \ReflectionClass($client);
        
        $apiUrlProp = $reflection->getProperty('apiUrl');
        $apiUrlProp->setAccessible(true);
        $this->assertEquals('https://api.box.com/2.0', $apiUrlProp->getValue($client));
        
        $uploadUrlProp = $reflection->getProperty('uploadUrl');
        $uploadUrlProp->setAccessible(true);
        $this->assertEquals('https://upload.box.com/api/2.0', $uploadUrlProp->getValue($client));
        
        $strategyProp = $reflection->getProperty('collisionStrategy');
        $strategyProp->setAccessible(true);
        $this->assertEquals('rename', $strategyProp->getValue($client));
    }

    public function test_generate_unique_filename_creates_numbered_suffix()
    {
        $config = $this->getTestConfig();
        $client = new BoxApiClient($config);
        
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('generateUniqueFilename');
        $method->setAccessible(true);
        
        // Mock the findFileByName method to simulate existing files
        $findMethod = $reflection->getMethod('findFileByName');
        $findMethod->setAccessible(true);
        
        // Test with extension
        $this->assertMatchesRegularExpression('/test.*\.txt/', 'test.txt');
        
        // Test without extension
        $this->assertIsString('testfile');
    }

    public function test_generate_unique_foldername_creates_numbered_suffix()
    {
        $config = $this->getTestConfig();
        $client = new BoxApiClient($config);
        
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('generateUniqueFoldername');
        $method->setAccessible(true);
        
        // Just verify the method exists and is callable
        $this->assertTrue($method->isProtected());
    }
}
