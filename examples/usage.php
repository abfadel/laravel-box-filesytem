<?php

/**
 * Example usage of the Laravel Box.com Filesystem Adapter
 * 
 * This file demonstrates all the features supported by the package.
 */

require 'vendor/autoload.php';

use Illuminate\Support\Facades\Storage;

// ============================================================================
// BASIC FILE OPERATIONS
// ============================================================================

// Upload a file
Storage::disk('box')->put('documents/report.pdf', file_get_contents('local-report.pdf'));

// Upload with specific collision strategy
Storage::disk('box')->put('documents/report.pdf', $contents, [
    'collision_strategy' => 'rename' // Will create 'report (1).pdf' if exists
]);

// Read file contents
$contents = Storage::disk('box')->get('documents/report.pdf');

// Read as stream
$stream = Storage::disk('box')->readStream('documents/report.pdf');

// Check if file exists
if (Storage::disk('box')->exists('documents/report.pdf')) {
    echo "File exists!\n";
}

// Get file metadata
$size = Storage::disk('box')->size('documents/report.pdf');
$lastModified = Storage::disk('box')->lastModified('documents/report.pdf');
$mimeType = Storage::disk('box')->mimeType('documents/report.pdf');

// Delete a file
Storage::disk('box')->delete('documents/old-report.pdf');

// Delete multiple files
Storage::disk('box')->delete(['file1.txt', 'file2.txt', 'file3.txt']);

// ============================================================================
// FILE OPERATIONS - COPY & MOVE
// ============================================================================

// Copy a file
Storage::disk('box')->copy('documents/report.pdf', 'archives/report-backup.pdf');

// Move/rename a file
Storage::disk('box')->move('documents/report.pdf', 'documents/2024-report.pdf');

// ============================================================================
// DIRECTORY OPERATIONS
// ============================================================================

// Create a directory
Storage::disk('box')->makeDirectory('projects/2024');

// Create nested directories
Storage::disk('box')->makeDirectory('projects/2024/q1/reports');

// Check if directory exists
if (Storage::disk('box')->directoryExists('projects/2024')) {
    echo "Directory exists!\n";
}

// List files in directory (non-recursive)
$files = Storage::disk('box')->files('projects/2024');

// List all files recursively
$allFiles = Storage::disk('box')->allFiles('projects/2024');

// List directories in directory (non-recursive)
$directories = Storage::disk('box')->directories('projects');

// List all directories recursively
$allDirectories = Storage::disk('box')->allDirectories('projects');

// Delete a directory
Storage::disk('box')->deleteDirectory('projects/old-project');

// ============================================================================
// ADVANCED BOX API FEATURES
// ============================================================================

// Access the Box API client for advanced features
$adapter = Storage::disk('box')->getAdapter();
$client = $adapter->getClient();

// Create a shared link
$fileInfo = $client->getFileInfo($fileId);
$shareLink = $client->createFileShareLink($fileId, [
    'access' => 'open', // 'open', 'company', 'collaborators'
    'password' => 'secret123', // Optional
    'unshared_at' => '2024-12-31T23:59:59-00:00', // Optional expiration
]);

echo "Share URL: " . $shareLink['shared_link']['url'] . "\n";

// Upload a new version of existing file
$client->uploadFileVersion($fileId, $newContents, 'updated-filename.txt');

// Get detailed file information
$fileInfo = $client->getFileInfo($fileId);
echo "File name: " . $fileInfo['name'] . "\n";
echo "File size: " . $fileInfo['size'] . " bytes\n";
echo "Modified: " . $fileInfo['modified_at'] . "\n";

// Get detailed folder information
$folderInfo = $client->getFolderInfo($folderId);
echo "Folder name: " . $folderInfo['name'] . "\n";
echo "Item count: " . $folderInfo['item_collection']['total_count'] . "\n";

// Move a folder
$client->moveFolder($folderId, $newParentFolderId);

// Copy a folder
$client->copyFolder($folderId, $destinationParentFolderId, 'Copied Folder');

// Search for files and folders
$results = $client->search('quarterly report', 'file');
foreach ($results['entries'] as $entry) {
    echo "Found: " . $entry['name'] . " (ID: " . $entry['id'] . ")\n";
}

// ============================================================================
// COLLISION HANDLING EXAMPLES
// ============================================================================

// Strategy 1: Rename (default) - Creates 'file (1).txt', 'file (2).txt', etc.
Storage::disk('box')->put('data/file.txt', $contents, [
    'collision_strategy' => 'rename'
]);

// Strategy 2: Overwrite - Replaces existing file
Storage::disk('box')->put('data/file.txt', $contents, [
    'collision_strategy' => 'overwrite'
]);

// Strategy 3: Skip - Keeps existing file, doesn't upload
Storage::disk('box')->put('data/file.txt', $contents, [
    'collision_strategy' => 'skip'
]);

// ============================================================================
// LARAVEL INTEGRATION EXAMPLES
// ============================================================================

// Store uploaded file from HTTP request
Route::post('/upload', function (Request $request) {
    $path = $request->file('document')->store('uploads', 'box');
    
    return response()->json([
        'success' => true,
        'path' => $path
    ]);
});

// Download file with proper headers
Route::get('/download/{path}', function ($path) {
    return Storage::disk('box')->download($path);
});

// Generate temporary download URL (share link)
Route::get('/share/{fileId}', function ($fileId) {
    $adapter = Storage::disk('box')->getAdapter();
    $client = $adapter->getClient();
    
    $result = $client->createFileShareLink($fileId, [
        'access' => 'open',
        'unshared_at' => now()->addDays(7)->toIso8601String(),
    ]);
    
    return response()->json([
        'share_url' => $result['shared_link']['url'],
        'expires_at' => $result['shared_link']['unshared_at'],
    ]);
});

// Sync local directory to Box
Route::post('/sync-to-box', function () {
    $localFiles = Storage::disk('local')->allFiles('exports');
    
    foreach ($localFiles as $file) {
        $contents = Storage::disk('local')->get($file);
        Storage::disk('box')->put($file, $contents);
    }
    
    return response()->json(['synced' => count($localFiles)]);
});

// List all files in a Box folder with metadata
Route::get('/list/{folder}', function ($folder) {
    $files = Storage::disk('box')->allFiles($folder);
    
    $fileList = [];
    foreach ($files as $file) {
        $fileList[] = [
            'path' => $file,
            'size' => Storage::disk('box')->size($file),
            'modified' => Storage::disk('box')->lastModified($file),
        ];
    }
    
    return response()->json($fileList);
});
