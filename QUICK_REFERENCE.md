# Quick Reference Guide

## Installation

```bash
composer require abfadel/laravel-box-api-adapter
php artisan vendor:publish --tag=box-config
```

## Configuration

Add to `.env`:
```env
BOX_CLIENT_ID=your_client_id
BOX_CLIENT_SECRET=your_client_secret
BOX_ENTERPRISE_ID=your_enterprise_id
BOX_KEY_ID=your_key_id
BOX_PRIVATE_KEY=/path/to/private_key.pem
BOX_PRIVATE_KEY_PASSWORD=your_password
BOX_COLLISION_STRATEGY=rename
```

Add to `config/filesystems.php`:
```php
'disks' => [
    'box' => [
        'driver' => 'box',
        'client_id' => env('BOX_CLIENT_ID'),
        'client_secret' => env('BOX_CLIENT_SECRET'),
        'enterprise_id' => env('BOX_ENTERPRISE_ID'),
        'private_key' => env('BOX_PRIVATE_KEY'),
        'private_key_password' => env('BOX_PRIVATE_KEY_PASSWORD'),
        'key_id' => env('BOX_KEY_ID'),
        'root_folder_id' => env('BOX_ROOT_FOLDER_ID', '0'),
        'collision_strategy' => env('BOX_COLLISION_STRATEGY', 'rename'),
    ],
],
```

## Basic Usage

### Files
```php
use Illuminate\Support\Facades\Storage;

// Upload
Storage::disk('box')->put('path/file.txt', 'contents');
Storage::disk('box')->put('file.txt', $contents, ['collision_strategy' => 'rename']);

// Download
$contents = Storage::disk('box')->get('path/file.txt');
$stream = Storage::disk('box')->readStream('path/file.txt');

// Check existence
Storage::disk('box')->exists('path/file.txt');

// Delete
Storage::disk('box')->delete('path/file.txt');

// Copy
Storage::disk('box')->copy('from.txt', 'to.txt');

// Move/Rename
Storage::disk('box')->move('old.txt', 'new.txt');

// Metadata
$size = Storage::disk('box')->size('path/file.txt');
$time = Storage::disk('box')->lastModified('path/file.txt');
$mime = Storage::disk('box')->mimeType('path/file.txt');
```

### Directories
```php
// Create
Storage::disk('box')->makeDirectory('folder/subfolder');

// List
$files = Storage::disk('box')->files('folder');
$allFiles = Storage::disk('box')->allFiles('folder'); // recursive

$dirs = Storage::disk('box')->directories('folder');
$allDirs = Storage::disk('box')->allDirectories('folder'); // recursive

// Check existence
Storage::disk('box')->directoryExists('folder');

// Delete
Storage::disk('box')->deleteDirectory('folder');
```

## Advanced Features

### Share Links
```php
$adapter = Storage::disk('box')->getAdapter();
$client = $adapter->getClient();

$fileId = '123456789';
$result = $client->createFileShareLink($fileId, [
    'access' => 'open', // 'open', 'company', 'collaborators'
    'password' => 'secret',
    'unshared_at' => '2024-12-31T23:59:59-00:00',
]);

$shareUrl = $result['shared_link']['url'];
```

### File Versioning
```php
$adapter = Storage::disk('box')->getAdapter();
$client = $adapter->getClient();

$fileId = '123456789';
$client->uploadFileVersion($fileId, $newContents, 'filename.txt');
```

### Search
```php
$adapter = Storage::disk('box')->getAdapter();
$client = $adapter->getClient();

$results = $client->search('query text', 'file', 100, 0);
```

### Collision Strategies

**Rename** (default): Creates `file (1).txt`, `file (2).txt`, etc.
```php
Storage::disk('box')->put('file.txt', $contents, ['collision_strategy' => 'rename']);
```

**Overwrite**: Replaces existing file
```php
Storage::disk('box')->put('file.txt', $contents, ['collision_strategy' => 'overwrite']);
```

**Skip**: Keeps existing, skips upload
```php
Storage::disk('box')->put('file.txt', $contents, ['collision_strategy' => 'skip']);
```

## Laravel Integration

### Upload from Request
```php
public function upload(Request $request)
{
    $path = $request->file('document')->store('uploads', 'box');
    return response()->json(['path' => $path]);
}
```

### Download
```php
public function download($path)
{
    return Storage::disk('box')->download($path);
}
```

## API Methods

### BoxApiClient Methods
- `uploadFile($parentFolderId, $filename, $content, $collisionStrategy = null)`
- `uploadFileVersion($fileId, $content, $filename = null)`
- `downloadFile($fileId)`
- `renameFile($fileId, $newName)`
- `deleteFile($fileId)`
- `createFileShareLink($fileId, $options = [])`
- `getFileInfo($fileId)`
- `createFolder($parentFolderId, $folderName, $collisionStrategy = null)`
- `renameFolder($folderId, $newName)`
- `deleteFolder($folderId, $recursive = false)`
- `moveFolder($folderId, $newParentId)`
- `moveFile($fileId, $newParentId)`
- `listFolderContents($folderId, $limit = 1000, $offset = 0)`
- `getFolderInfo($folderId)`
- `copyFile($fileId, $parentFolderId, $name = null)`
- `copyFolder($folderId, $parentFolderId, $name = null)`
- `search($query, $type = null, $limit = 100, $offset = 0)`

## Supported Storage Facade Methods
- `put()`, `putFile()`, `putFileAs()`
- `get()`, `readStream()`
- `exists()`, `missing()`
- `delete()`
- `copy()`, `move()`
- `size()`, `lastModified()`, `mimeType()`
- `makeDirectory()`, `deleteDirectory()`
- `files()`, `allFiles()`, `directories()`, `allDirectories()`
- `directoryExists()`, `fileExists()`
- `download()`, `response()`

## Requirements
- PHP 8.0+
- Laravel 9.x, 10.x, or 11.x
- Box.com account with JWT authentication

## Testing
```bash
composer test
```
