<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Box.com API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Box.com API credentials for JWT authentication.
    | You'll need to create an app in Box Developer Console and download
    | the JSON configuration file.
    |
    */

    'client_id' => env('BOX_CLIENT_ID'),
    'client_secret' => env('BOX_CLIENT_SECRET'),
    'enterprise_id' => env('BOX_ENTERPRISE_ID'),
    
    // Path to JWT private key file or the key content
    'private_key' => env('BOX_PRIVATE_KEY'),
    'private_key_password' => env('BOX_PRIVATE_KEY_PASSWORD'),
    'key_id' => env('BOX_KEY_ID'),
    
    // JWT token settings
    'token_ttl' => 60, // seconds
    
    // Box API configuration
    'api_url' => env('BOX_API_URL', 'https://api.box.com/2.0'),
    'upload_url' => env('BOX_UPLOAD_URL', 'https://upload.box.com/api/2.0'),
    'auth_url' => env('BOX_AUTH_URL', 'https://api.box.com/oauth2/token'),
    
    // Default collision strategy: 'rename', 'overwrite', or 'skip'
    'collision_strategy' => env('BOX_COLLISION_STRATEGY', 'rename'),
    
    // Root folder ID (0 for root)
    'root_folder_id' => env('BOX_ROOT_FOLDER_ID', '0'),
];
