<?php

namespace Abfadel\BoxAdapter;

use Abfadel\BoxAdapter\Adapter\BoxAdapter;
use Abfadel\BoxAdapter\Api\BoxApiClient;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class BoxServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/box.php',
            'box'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/box.php' => config_path('box.php'),
        ], 'box-config');

        // Register Box disk driver
        Storage::extend('box', function ($app, $config) {
            $boxConfig = array_merge(config('box', []), $config);
            
            $client = new BoxApiClient($boxConfig);
            $adapter = new BoxAdapter($client, $boxConfig['root_folder_id'] ?? '0');
            
            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
}
