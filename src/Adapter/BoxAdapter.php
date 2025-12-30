<?php

namespace Abfadel\BoxAdapter\Adapter;

use Abfadel\BoxAdapter\Api\BoxApiClient;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;

class BoxAdapter implements FilesystemAdapter
{
    protected BoxApiClient $client;
    protected string $rootFolderId;
    protected array $pathCache = [];

    public function __construct(BoxApiClient $client, string $rootFolderId = '0')
    {
        $this->client = $client;
        $this->rootFolderId = $rootFolderId;
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->getFileId($path);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $this->getFolderId($path);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $pathInfo = $this->parsePath($path);
            $parentId = $this->ensureFolderExists($pathInfo['dir']);
            
            $result = $this->client->uploadFile(
                $parentId,
                $pathInfo['basename'],
                $contents,
                $config->get('collision_strategy', 'rename')
            );
            
            if (!isset($result['entries'][0]['id'])) {
                throw new \RuntimeException('Upload failed: no file ID returned');
            }
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $pathInfo = $this->parsePath($path);
            $parentId = $this->ensureFolderExists($pathInfo['dir']);
            
            $result = $this->client->uploadFile(
                $parentId,
                $pathInfo['basename'],
                $contents,
                $config->get('collision_strategy', 'rename')
            );
            
            if (!isset($result['entries'][0]['id'])) {
                throw new \RuntimeException('Upload failed: no file ID returned');
            }
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function read(string $path): string
    {
        try {
            $fileId = $this->getFileId($path);
            $stream = $this->client->downloadFile($fileId);
            return $stream->getContents();
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $fileId = $this->getFileId($path);
            return $this->client->downloadFile($fileId)->detach();
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $fileId = $this->getFileId($path);
            $this->client->deleteFile($fileId);
            $this->clearPathCache($path);
        } catch (\Exception $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $folderId = $this->getFolderId($path);
            $this->client->deleteFolder($folderId, true);
            $this->clearPathCache($path);
        } catch (\Exception $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->ensureFolderExists($path);
        } catch (\Exception $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage());
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Box does not support visibility settings');
    }

    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path, 'Box does not support visibility settings');
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $fileId = $this->getFileId($path);
            $info = $this->client->getFileInfo($fileId);
            
            return new FileAttributes(
                $path,
                $info['size'] ?? null,
                null,
                isset($info['modified_at']) ? strtotime($info['modified_at']) : null,
                $info['mime_type'] ?? null
            );
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $fileId = $this->getFileId($path);
            $info = $this->client->getFileInfo($fileId);
            
            return new FileAttributes(
                $path,
                $info['size'] ?? null,
                null,
                isset($info['modified_at']) ? strtotime($info['modified_at']) : null
            );
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage(), $e);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $fileId = $this->getFileId($path);
            $info = $this->client->getFileInfo($fileId);
            
            return new FileAttributes(
                $path,
                $info['size'] ?? null
            );
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $folderId = $path === '' || $path === '/' ? $this->rootFolderId : $this->getFolderId($path);
            $result = $this->client->listFolderContents($folderId);
            
            foreach ($result['entries'] ?? [] as $entry) {
                $itemPath = $path === '' || $path === '/' ? $entry['name'] : $path . '/' . $entry['name'];
                
                if ($entry['type'] === 'file') {
                    yield new FileAttributes(
                        $itemPath,
                        $entry['size'] ?? null,
                        null,
                        isset($entry['modified_at']) ? strtotime($entry['modified_at']) : null
                    );
                } elseif ($entry['type'] === 'folder') {
                    yield new DirectoryAttributes($itemPath);
                    
                    if ($deep) {
                        yield from $this->listContents($itemPath, true);
                    }
                }
            }
        } catch (\Exception $e) {
            // Return empty iterator on error
            return;
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $fileId = $this->getFileId($source);
            $destPathInfo = $this->parsePath($destination);
            
            // Ensure destination folder exists
            $destParentId = $this->ensureFolderExists($destPathInfo['dir']);
            
            // Move the file
            $this->client->moveFile($fileId, $destParentId);
            
            // Rename if necessary
            if ($destPathInfo['basename'] !== basename($source)) {
                $this->client->renameFile($fileId, $destPathInfo['basename']);
            }
            
            $this->clearPathCache($source);
            $this->clearPathCache($destination);
        } catch (\Exception $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $fileId = $this->getFileId($source);
            $destPathInfo = $this->parsePath($destination);
            
            // Ensure destination folder exists
            $destParentId = $this->ensureFolderExists($destPathInfo['dir']);
            
            // Copy the file
            $this->client->copyFile($fileId, $destParentId, $destPathInfo['basename']);
        } catch (\Exception $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * Get file ID from path
     */
    protected function getFileId(string $path): string
    {
        if (isset($this->pathCache[$path]['file'])) {
            return $this->pathCache[$path]['file'];
        }

        $pathInfo = $this->parsePath($path);
        $parentId = $this->getFolderId($pathInfo['dir']);
        
        $contents = $this->client->listFolderContents($parentId);
        
        foreach ($contents['entries'] ?? [] as $entry) {
            if ($entry['type'] === 'file' && $entry['name'] === $pathInfo['basename']) {
                $this->pathCache[$path] = ['file' => $entry['id']];
                return $entry['id'];
            }
        }
        
        throw new \RuntimeException("File not found: {$path}");
    }

    /**
     * Get folder ID from path
     */
    protected function getFolderId(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $this->rootFolderId;
        }

        if (isset($this->pathCache[$path]['folder'])) {
            return $this->pathCache[$path]['folder'];
        }

        $parts = explode('/', trim($path, '/'));
        $currentFolderId = $this->rootFolderId;
        $currentPath = '';
        
        foreach ($parts as $part) {
            $currentPath .= ($currentPath ? '/' : '') . $part;
            
            if (isset($this->pathCache[$currentPath]['folder'])) {
                $currentFolderId = $this->pathCache[$currentPath]['folder'];
                continue;
            }
            
            $contents = $this->client->listFolderContents($currentFolderId);
            $found = false;
            
            foreach ($contents['entries'] ?? [] as $entry) {
                if ($entry['type'] === 'folder' && $entry['name'] === $part) {
                    $currentFolderId = $entry['id'];
                    $this->pathCache[$currentPath] = ['folder' => $entry['id']];
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                throw new \RuntimeException("Folder not found: {$currentPath}");
            }
        }
        
        return $currentFolderId;
    }

    /**
     * Ensure folder path exists, creating folders as needed
     */
    protected function ensureFolderExists(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $this->rootFolderId;
        }

        try {
            return $this->getFolderId($path);
        } catch (\RuntimeException $e) {
            // Folder doesn't exist, create it
            $parts = explode('/', trim($path, '/'));
            $currentFolderId = $this->rootFolderId;
            $currentPath = '';
            
            foreach ($parts as $part) {
                $currentPath .= ($currentPath ? '/' : '') . $part;
                
                try {
                    $currentFolderId = $this->getFolderId($currentPath);
                } catch (\RuntimeException $e) {
                    $result = $this->client->createFolder($currentFolderId, $part, 'skip');
                    $currentFolderId = $result['id'];
                    $this->pathCache[$currentPath] = ['folder' => $currentFolderId];
                }
            }
            
            return $currentFolderId;
        }
    }

    /**
     * Parse path into directory and basename
     */
    protected function parsePath(string $path): array
    {
        $path = trim($path, '/');
        $lastSlash = strrpos($path, '/');
        
        if ($lastSlash === false) {
            return [
                'dir' => '',
                'basename' => $path,
            ];
        }
        
        return [
            'dir' => substr($path, 0, $lastSlash),
            'basename' => substr($path, $lastSlash + 1),
        ];
    }

    /**
     * Clear path from cache and all parent paths
     */
    protected function clearPathCache(string $path): void
    {
        unset($this->pathCache[$path]);
        
        // Clear all ancestor paths from cache
        $pathInfo = $this->parsePath($path);
        $currentPath = $pathInfo['dir'];
        
        while ($currentPath !== '' && $currentPath !== '/') {
            unset($this->pathCache[$currentPath]);
            $pathInfo = $this->parsePath($currentPath);
            $currentPath = $pathInfo['dir'];
        }
    }

    /**
     * Get the Box API client
     */
    public function getClient(): BoxApiClient
    {
        return $this->client;
    }

    /**
     * Get file ID from path (public helper method)
     */
    public function getFileIdFromPath(string $path): string
    {
        return $this->getFileId($path);
    }

    /**
     * Get folder ID from path (public helper method)
     */
    public function getFolderIdFromPath(string $path): string
    {
        return $this->getFolderId($path);
    }
}
