<?php

namespace App\Providers;

use App\Contracts\Aws\StorageServiceInterface;
use App\Contracts\Repositories\DocumentRepositoryInterface;
use App\Repositories\DocumentRepository;
use App\Services\Aws\S3StorageService;
use Aws\S3\S3Client;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // AWS S3 Service
        $this->app->singleton(S3Client::class, function () {
            return new S3Client([
                'version' => 'latest',
                'region' => config('filesystems.disks.s3.region'),
                'credentials' => [
                    'key' => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);
        });
        
        $this->app->singleton(StorageServiceInterface::class, function ($app) {
            return new S3StorageService(
                $app->make(S3Client::class),
                config('filesystems.disks.s3.bucket')
            );
        });
        
        // Repository bindings
        $this->app->singleton(DocumentRepositoryInterface::class, DocumentRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
