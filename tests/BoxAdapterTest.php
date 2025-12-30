<?php

namespace Abfadel\BoxAdapter\Tests;

use Abfadel\BoxAdapter\Adapter\BoxAdapter;
use Abfadel\BoxAdapter\Api\BoxApiClient;
use PHPUnit\Framework\TestCase;

class BoxAdapterTest extends TestCase
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

    public function test_adapter_can_be_instantiated()
    {
        $config = $this->getTestConfig();
        $client = new BoxApiClient($config);
        $adapter = new BoxAdapter($client, '0');
        
        $this->assertInstanceOf(BoxAdapter::class, $adapter);
    }

    public function test_adapter_returns_client()
    {
        $config = $this->getTestConfig();
        $client = new BoxApiClient($config);
        $adapter = new BoxAdapter($client, '0');
        
        $this->assertSame($client, $adapter->getClient());
    }

    public function test_adapter_parses_path_correctly()
    {
        $config = $this->getTestConfig();
        $client = new BoxApiClient($config);
        $adapter = new BoxAdapter($client, '0');
        
        $reflection = new \ReflectionClass($adapter);
        $method = $reflection->getMethod('parsePath');
        $method->setAccessible(true);
        
        // Test path with directory
        $result = $method->invoke($adapter, 'folder/subfolder/file.txt');
        $this->assertEquals('folder/subfolder', $result['dir']);
        $this->assertEquals('file.txt', $result['basename']);
        
        // Test path without directory
        $result = $method->invoke($adapter, 'file.txt');
        $this->assertEquals('', $result['dir']);
        $this->assertEquals('file.txt', $result['basename']);
        
        // Test path with leading/trailing slashes
        $result = $method->invoke($adapter, '/folder/file.txt/');
        $this->assertEquals('folder', $result['dir']);
        $this->assertEquals('file.txt', $result['basename']);
    }

    public function test_adapter_clears_path_cache()
    {
        $config = $this->getTestConfig();
        $client = new BoxApiClient($config);
        $adapter = new BoxAdapter($client, '0');
        
        $reflection = new \ReflectionClass($adapter);
        
        // Set cache
        $cacheProp = $reflection->getProperty('pathCache');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue($adapter, [
            'test/path' => ['file' => '123'],
            'test' => ['folder' => '456'],
        ]);
        
        // Clear cache
        $method = $reflection->getMethod('clearPathCache');
        $method->setAccessible(true);
        $method->invoke($adapter, 'test/path');
        
        $cache = $cacheProp->getValue($adapter);
        
        // The path and its parent should be cleared
        $this->assertArrayNotHasKey('test/path', $cache);
        $this->assertArrayNotHasKey('test', $cache);
    }
}
