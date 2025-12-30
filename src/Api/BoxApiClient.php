<?php

namespace Abfadel\BoxAdapter\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;

class BoxApiClient
{
    protected BoxJwtAuth $auth;
    protected Client $client;
    protected string $apiUrl;
    protected string $uploadUrl;
    protected string $collisionStrategy;

    public function __construct(array $config)
    {
        $this->auth = new BoxJwtAuth($config);
        $this->client = new Client();
        $this->apiUrl = $config['api_url'];
        $this->uploadUrl = $config['upload_url'];
        $this->collisionStrategy = $config['collision_strategy'] ?? 'rename';
    }

    /**
     * Make an API request to Box
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        $url = str_starts_with($endpoint, 'http') ? $endpoint : $this->apiUrl . $endpoint;
        
        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $this->auth->getAccessToken(),
        ]);

        try {
            $response = $this->client->request($method, $url, $options);
            $content = $response->getBody()->getContents();
            
            return $content ? json_decode($content, true) : [];
        } catch (GuzzleException $e) {
            $message = $e->getMessage();
            
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($errorBody, true);
                $message = $errorData['message'] ?? $errorData['error_description'] ?? $message;
            }
            
            throw new \RuntimeException("Box API error: {$message}", $e->getCode(), $e);
        }
    }

    /**
     * Upload a file (initial upload)
     */
    public function uploadFile(string $parentFolderId, string $filename, $content, string $collisionStrategy = null): array
    {
        $strategy = $collisionStrategy ?? $this->collisionStrategy;
        
        // Check for existing file
        if ($strategy !== 'overwrite') {
            $existing = $this->findFileByName($parentFolderId, $filename);
            
            if ($existing) {
                if ($strategy === 'skip') {
                    return $existing;
                } elseif ($strategy === 'rename') {
                    $filename = $this->generateUniqueFilename($parentFolderId, $filename);
                }
            }
        }

        $attributes = [
            'name' => $filename,
            'parent' => ['id' => $parentFolderId],
        ];

        $multipart = [
            [
                'name' => 'attributes',
                'contents' => json_encode($attributes),
            ],
            [
                'name' => 'file',
                'contents' => is_resource($content) ? $content : Utils::streamFor($content),
                'filename' => $filename,
            ],
        ];

        return $this->request('POST', $this->uploadUrl . '/files/content', [
            'multipart' => $multipart,
        ]);
    }

    /**
     * Upload a new version of an existing file
     */
    public function uploadFileVersion(string $fileId, $content, string $filename = null): array
    {
        $multipart = [
            [
                'name' => 'file',
                'contents' => is_resource($content) ? $content : Utils::streamFor($content),
                'filename' => $filename ?? 'file',
            ],
        ];

        if ($filename) {
            $multipart[] = [
                'name' => 'attributes',
                'contents' => json_encode(['name' => $filename]),
            ];
        }

        return $this->request('POST', $this->uploadUrl . "/files/{$fileId}/content", [
            'multipart' => $multipart,
        ]);
    }

    /**
     * Download a file
     */
    public function downloadFile(string $fileId)
    {
        $url = $this->apiUrl . "/files/{$fileId}/content";
        
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->auth->getAccessToken(),
            ],
            'stream' => true,
        ];

        try {
            $response = $this->client->request('GET', $url, $options);
            return $response->getBody();
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Failed to download file: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Rename a file
     */
    public function renameFile(string $fileId, string $newName): array
    {
        return $this->request('PUT', "/files/{$fileId}", [
            'json' => ['name' => $newName],
        ]);
    }

    /**
     * Delete a file
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->request('DELETE', "/files/{$fileId}");
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Create a shared link for a file
     */
    public function createFileShareLink(string $fileId, array $options = []): array
    {
        $sharedLink = [
            'access' => $options['access'] ?? 'open', // open, company, collaborators
        ];

        if (isset($options['password'])) {
            $sharedLink['password'] = $options['password'];
        }

        if (isset($options['unshared_at'])) {
            $sharedLink['unshared_at'] = $options['unshared_at'];
        }

        return $this->request('PUT', "/files/{$fileId}", [
            'json' => ['shared_link' => $sharedLink],
        ]);
    }

    /**
     * Get file information
     */
    public function getFileInfo(string $fileId): array
    {
        return $this->request('GET', "/files/{$fileId}");
    }

    /**
     * Create a folder
     */
    public function createFolder(string $parentFolderId, string $folderName, string $collisionStrategy = null): array
    {
        $strategy = $collisionStrategy ?? $this->collisionStrategy;
        
        // Check for existing folder
        if ($strategy !== 'overwrite') {
            $existing = $this->findFolderByName($parentFolderId, $folderName);
            
            if ($existing) {
                if ($strategy === 'skip') {
                    return $existing;
                } elseif ($strategy === 'rename') {
                    $folderName = $this->generateUniqueFoldername($parentFolderId, $folderName);
                }
            }
        }

        return $this->request('POST', '/folders', [
            'json' => [
                'name' => $folderName,
                'parent' => ['id' => $parentFolderId],
            ],
        ]);
    }

    /**
     * Rename a folder
     */
    public function renameFolder(string $folderId, string $newName): array
    {
        return $this->request('PUT', "/folders/{$folderId}", [
            'json' => ['name' => $newName],
        ]);
    }

    /**
     * Delete a folder
     */
    public function deleteFolder(string $folderId, bool $recursive = false): bool
    {
        try {
            $this->request('DELETE', "/folders/{$folderId}", [
                'query' => ['recursive' => $recursive ? 'true' : 'false'],
            ]);
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Move a folder
     */
    public function moveFolder(string $folderId, string $newParentId): array
    {
        return $this->request('PUT', "/folders/{$folderId}", [
            'json' => [
                'parent' => ['id' => $newParentId],
            ],
        ]);
    }

    /**
     * Move a file
     */
    public function moveFile(string $fileId, string $newParentId): array
    {
        return $this->request('PUT', "/files/{$fileId}", [
            'json' => [
                'parent' => ['id' => $newParentId],
            ],
        ]);
    }

    /**
     * List folder contents
     */
    public function listFolderContents(string $folderId, int $limit = 1000, int $offset = 0): array
    {
        return $this->request('GET', "/folders/{$folderId}/items", [
            'query' => [
                'limit' => $limit,
                'offset' => $offset,
                'fields' => 'id,name,type,size,modified_at,created_at,path_collection,parent',
            ],
        ]);
    }

    /**
     * Get folder information
     */
    public function getFolderInfo(string $folderId): array
    {
        return $this->request('GET', "/folders/{$folderId}");
    }

    /**
     * Find a file by name in a folder
     */
    protected function findFileByName(string $folderId, string $filename): ?array
    {
        $contents = $this->listFolderContents($folderId);
        
        foreach ($contents['entries'] ?? [] as $entry) {
            if ($entry['type'] === 'file' && $entry['name'] === $filename) {
                return $entry;
            }
        }
        
        return null;
    }

    /**
     * Find a folder by name in a parent folder
     */
    protected function findFolderByName(string $parentFolderId, string $folderName): ?array
    {
        $contents = $this->listFolderContents($parentFolderId);
        
        foreach ($contents['entries'] ?? [] as $entry) {
            if ($entry['type'] === 'folder' && $entry['name'] === $folderName) {
                return $entry;
            }
        }
        
        return null;
    }

    /**
     * Generate a unique filename by appending a number
     */
    protected function generateUniqueFilename(string $folderId, string $filename): string
    {
        $info = pathinfo($filename);
        $basename = $info['filename'];
        $extension = isset($info['extension']) ? '.' . $info['extension'] : '';
        
        $counter = 1;
        $newFilename = $filename;
        
        while ($this->findFileByName($folderId, $newFilename)) {
            $newFilename = "{$basename} ({$counter}){$extension}";
            $counter++;
        }
        
        return $newFilename;
    }

    /**
     * Generate a unique folder name by appending a number
     */
    protected function generateUniqueFoldername(string $parentFolderId, string $folderName): string
    {
        $counter = 1;
        $newFolderName = $folderName;
        
        while ($this->findFolderByName($parentFolderId, $newFolderName)) {
            $newFolderName = "{$folderName} ({$counter})";
            $counter++;
        }
        
        return $newFolderName;
    }

    /**
     * Copy a file
     */
    public function copyFile(string $fileId, string $parentFolderId, string $name = null): array
    {
        $data = [
            'parent' => ['id' => $parentFolderId],
        ];
        
        if ($name) {
            $data['name'] = $name;
        }

        return $this->request('POST', "/files/{$fileId}/copy", [
            'json' => $data,
        ]);
    }

    /**
     * Copy a folder
     */
    public function copyFolder(string $folderId, string $parentFolderId, string $name = null): array
    {
        $data = [
            'parent' => ['id' => $parentFolderId],
        ];
        
        if ($name) {
            $data['name'] = $name;
        }

        return $this->request('POST', "/folders/{$folderId}/copy", [
            'json' => $data,
        ]);
    }

    /**
     * Search for files and folders
     */
    public function search(string $query, string $type = null, int $limit = 100, int $offset = 0): array
    {
        $params = [
            'query' => $query,
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($type) {
            $params['type'] = $type; // file or folder
        }

        return $this->request('GET', '/search', [
            'query' => $params,
        ]);
    }
}
