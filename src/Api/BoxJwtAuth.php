<?php

namespace Abfadel\BoxAdapter\Api;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class BoxJwtAuth
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $enterpriseId;
    protected string $privateKey;
    protected string $privateKeyPassword;
    protected string $keyId;
    protected string $authUrl;
    protected int $tokenTtl;
    protected ?string $accessToken = null;
    protected ?int $tokenExpiresAt = null;

    public function __construct(array $config)
    {
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->enterpriseId = $config['enterprise_id'];
        $this->privateKey = $config['private_key'];
        $this->privateKeyPassword = $config['private_key_password'] ?? '';
        $this->keyId = $config['key_id'];
        $this->authUrl = $config['auth_url'];
        $this->tokenTtl = $config['token_ttl'] ?? 60;
    }

    /**
     * Get a valid access token, refreshing if necessary
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiresAt > time()) {
            return $this->accessToken;
        }

        return $this->refreshAccessToken();
    }

    /**
     * Generate JWT assertion and exchange for access token
     */
    protected function refreshAccessToken(): string
    {
        $assertion = $this->generateJwtAssertion();
        
        $client = new Client();
        
        try {
            $response = $client->post($this->authUrl, [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            $this->accessToken = $data['access_token'];
            $this->tokenExpiresAt = time() + ($data['expires_in'] ?? 3600) - 60; // 60s buffer
            
            return $this->accessToken;
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to authenticate with Box API: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate JWT assertion for Box authentication
     */
    protected function generateJwtAssertion(): string
    {
        $payload = [
            'iss' => $this->clientId,
            'sub' => $this->enterpriseId,
            'box_sub_type' => 'enterprise',
            'aud' => $this->authUrl,
            'jti' => bin2hex(random_bytes(16)),
            'exp' => time() + $this->tokenTtl,
        ];

        $headers = [
            'kid' => $this->keyId,
        ];

        // Load private key
        $privateKey = $this->loadPrivateKey();

        return JWT::encode($payload, $privateKey, 'RS256', null, $headers);
    }

    /**
     * Load the private key from file or string
     */
    protected function loadPrivateKey()
    {
        $keyContent = $this->privateKey;
        
        // If it's a file path, read it
        if (file_exists($keyContent)) {
            $keyContent = file_get_contents($keyContent);
        }

        if (empty($this->privateKeyPassword)) {
            return $keyContent;
        }

        // If password protected, decrypt it
        $key = openssl_pkey_get_private($keyContent, $this->privateKeyPassword);
        
        if ($key === false) {
            throw new \RuntimeException('Failed to load private key');
        }

        return $key;
    }

    /**
     * Invalidate the current token
     */
    public function invalidateToken(): void
    {
        $this->accessToken = null;
        $this->tokenExpiresAt = null;
    }
}
