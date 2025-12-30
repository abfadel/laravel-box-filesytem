# Laravel Box.com Filesystem Adapter

A Laravel filesystem adapter for Box.com with JWT authentication support. This package provides a complete integration with Box.com's API, allowing you to use Laravel's Storage facade to interact with Box.com files and folders.

## Features

### File Operations
- ✅ **Upload files** - Initial upload with versioning support
- ✅ **Download files** - Stream or retrieve file contents
- ✅ **Rename files** - Update file names
- ✅ **Delete files** - Remove files from Box
- ✅ **Share links** - Generate public/private share links
- ✅ **File info** - Retrieve file metadata (size, modified date, etc.)
- ✅ **Copy files** - Duplicate files to different locations
- ✅ **Move files** - Relocate files to different folders

### Folder Operations
- ✅ **Create folders** - Create new directories
- ✅ **Rename folders** - Update folder names
- ✅ **Delete folders** - Remove folders (recursive support)
- ✅ **Move folders** - Relocate folders
- ✅ **List contents** - Browse folder contents
- ✅ **Folder info** - Retrieve folder metadata

### Name Collision Handling
- ✅ **Rename strategy** - Automatically rename files/folders with numeric suffix
- ✅ **Overwrite strategy** - Replace existing files/folders
- ✅ **Skip strategy** - Keep existing files/folders, skip upload

### JWT Authentication
- ✅ Full JWT authentication support with automatic token refresh
- ✅ Secure private key handling (file or environment variable)
- ✅ Enterprise-level authentication

## Installation

Install the package via Composer:

```bash
composer require abfadel/laravel-box-api-adapter
```

### Laravel Auto-Discovery

The package will automatically register itself via Laravel's package auto-discovery feature. If you need to manually register it, add the service provider to `config/app.php`:

```php
'providers' => [
    // ...
    Abfadel\BoxAdapter\BoxServiceProvider::class,
],
```

### Publish Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=box-config
```

This will create `config/box.php` in your Laravel application.

## Box.com Setup

### 1. Create a Box Application

1. Go to [Box Developer Console](https://app.box.com/developers/console)
2. Create a new app with **Server Authentication (with JWT)** authentication type
3. Configure your app settings:
   - Set appropriate scopes (read/write access)
   - Add your application to your enterprise (if using enterprise account)

### 2. Generate Key Pair

1. In your Box app settings, go to "Configuration"
2. Scroll to "Add and Manage Public Keys"
3. Generate a new key pair
4. Download the JSON configuration file

### 3. Configure Environment Variables

Add the following to your `.env` file:

```env
BOX_CLIENT_ID=your_client_id
BOX_CLIENT_SECRET=your_client_secret
BOX_ENTERPRISE_ID=your_enterprise_id
BOX_KEY_ID=your_key_id

# Option 1: Path to private key file
BOX_PRIVATE_KEY=/path/to/private_key.pem

# Option 2: Private key content (base64 encoded or raw)
# BOX_PRIVATE_KEY="-----BEGIN ENCRYPTED PRIVATE KEY-----\n...\n-----END ENCRYPTED PRIVATE KEY-----"

# If your private key is password protected
BOX_PRIVATE_KEY_PASSWORD=your_key_password

# Optional: Collision strategy (rename, overwrite, skip)
BOX_COLLISION_STRATEGY=rename

# Optional: Root folder ID (0 for root, or specific folder ID)
BOX_ROOT_FOLDER_ID=0
```

### 4. Configure Filesystem

Add the Box disk to your `config/filesystems.php`:

```php
'disks' => [
    // ... other disks

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

## Usage

### Basic File Operations

```php
use Illuminate\Support\Facades\Storage;

// Upload a file
Storage::disk('box')->put('path/to/file.txt', 'Contents');

// Upload with custom collision strategy
Storage::disk('box')->put('path/to/file.txt', 'Contents', ['collision_strategy' => 'overwrite']);

// Download a file
$contents = Storage::disk('box')->get('path/to/file.txt');

// Download as stream
$stream = Storage::disk('box')->readStream('path/to/file.txt');

// Delete a file
Storage::disk('box')->delete('path/to/file.txt');

// Check if file exists
if (Storage::disk('box')->exists('path/to/file.txt')) {
    // File exists
}

// Get file size
$size = Storage::disk('box')->size('path/to/file.txt');

// Get last modified time
$timestamp = Storage::disk('box')->lastModified('path/to/file.txt');

// Copy a file
Storage::disk('box')->copy('path/to/file.txt', 'path/to/copy.txt');

// Move/rename a file
Storage::disk('box')->move('old/path.txt', 'new/path.txt');
```

### Directory Operations

```php
// Create a directory
Storage::disk('box')->makeDirectory('path/to/directory');

// Delete a directory
Storage::disk('box')->deleteDirectory('path/to/directory');

// List directory contents
$files = Storage::disk('box')->files('path/to/directory');
$allFiles = Storage::disk('box')->allFiles('path/to/directory'); // Recursive

$directories = Storage::disk('box')->directories('path/to/directory');
$allDirectories = Storage::disk('box')->allDirectories('path/to/directory'); // Recursive

// Check if directory exists
if (Storage::disk('box')->directoryExists('path/to/directory')) {
    // Directory exists
}
```

### Advanced Operations

```php
// Access the Box API client directly for advanced operations
$adapter = Storage::disk('box')->getAdapter();
$client = $adapter->getClient();

// Create a shared link
$result = $client->createFileShareLink('file_id', [
    'access' => 'open', // 'open', 'company', or 'collaborators'
    'password' => 'optional_password',
    'unshared_at' => '2024-12-31T23:59:59-00:00', // Optional expiration
]);

$shareUrl = $result['shared_link']['url'];

// Get file info
$fileInfo = $client->getFileInfo('file_id');

// Get folder info
$folderInfo = $client->getFolderInfo('folder_id');

// Upload a new version of a file
$client->uploadFileVersion('file_id', $contents, 'filename.txt');

// Search for files
$results = $client->search('query', 'file', 100, 0);

// Move a folder
$client->moveFolder('folder_id', 'new_parent_folder_id');

// Copy a folder
$client->copyFolder('folder_id', 'destination_parent_folder_id', 'new_folder_name');
```

### Collision Strategies

The package supports three collision strategies for handling name conflicts:

1. **rename** (default) - Appends a number to the filename/folder name
   ```php
   // file.txt becomes file (1).txt, file (2).txt, etc.
   Storage::disk('box')->put('file.txt', $contents, ['collision_strategy' => 'rename']);
   ```

2. **overwrite** - Replaces the existing file/folder
   ```php
   Storage::disk('box')->put('file.txt', $contents, ['collision_strategy' => 'overwrite']);
   ```

3. **skip** - Keeps the existing file/folder, skips the upload
   ```php
   Storage::disk('box')->put('file.txt', $contents, ['collision_strategy' => 'skip']);
   ```

## Laravel Integration Examples

### Store Uploaded Files

```php
use Illuminate\Http\Request;

public function upload(Request $request)
{
    $request->validate([
        'file' => 'required|file|max:10240',
    ]);

    $path = $request->file('file')->store('uploads', 'box');
    
    return response()->json(['path' => $path]);
}
```

### Download Files

```php
public function download($path)
{
    return Storage::disk('box')->download($path);
}
```

### Generate Share Link

```php
public function share($path)
{
    $adapter = Storage::disk('box')->getAdapter();
    $client = $adapter->getClient();
    
    // Get file ID from path
    $fileId = $adapter->getFileIdFromPath($path);
    
    // Create share link
    $result = $client->createFileShareLink($fileId, [
        'access' => 'open',
    ]);
    
    return response()->json([
        'share_url' => $result['shared_link']['url'],
    ]);
}
```

## Testing

```bash
composer test
```

## Security

If you discover any security-related issues, please email the package author instead of using the issue tracker.

## Requirements

- PHP 8.0 or higher
- Laravel 9.x, 10.x, or 11.x
- Box.com account with JWT authentication configured

## License

The MIT License (MIT). Please see License File for more information.

## Credits

- [abfadel](https://github.com/abfadel)

## Support

For issues, questions, or contributions, please use the [GitHub issue tracker](https://github.com/abfadel/laravel-box-api-adapter-/issues).