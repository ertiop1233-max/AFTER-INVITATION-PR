<?php

namespace App\Providers;

use App\Services\Storage\GoogleDriveProvider;
use App\Services\Storage\StorageProviderInterface;
use Illuminate\Support\ServiceProvider;

class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StorageProviderInterface::class, function ($app) {
            $config = $app['config']['memoryvault.google'];

            return new GoogleDriveProvider(
                serviceAccountKey: $config['service_account_key'] ?? null,
                serviceAccountKeyFile: $config['service_account_key_file'] ?? null,
                sharedDriveId: $config['shared_drive_id'] ?? null,
                storageRootFolderId: $config['storage_root_folder_id'] ?? null,
            );
        });
    }
}
