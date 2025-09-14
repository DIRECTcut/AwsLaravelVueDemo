<?php

namespace App\Providers;

use App\Contracts\Aws\DocumentAnalysisServiceInterface;
use App\Contracts\Aws\StorageServiceInterface;
use App\Contracts\Aws\TextAnalysisServiceInterface;
use App\Contracts\Repositories\DocumentRepositoryInterface;
use App\Repositories\DocumentRepository;
use App\Services\Aws\ComprehendService;
use App\Services\Aws\S3StorageService;
use App\Services\Aws\TextractService;
use App\Services\Processing\DocumentProcessorManager;
use App\Services\Processing\ImageDocumentProcessor;
use App\Services\Processing\PdfDocumentProcessor;
use App\Services\Processing\TextDocumentProcessor;
use Aws\Comprehend\ComprehendClient;
use Aws\S3\S3Client;
use Aws\Textract\TextractClient;
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
        
        // AWS Textract Service
        $this->app->singleton(TextractClient::class, function () {
            return new TextractClient([
                'version' => 'latest',
                'region' => config('filesystems.disks.s3.region'),
                'credentials' => [
                    'key' => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);
        });
        
        $this->app->singleton(DocumentAnalysisServiceInterface::class, function ($app) {
            return new TextractService(
                $app->make(TextractClient::class),
                $app->make('log')
            );
        });
        
        // AWS Comprehend Service
        $this->app->singleton(ComprehendClient::class, function () {
            return new ComprehendClient([
                'version' => 'latest',
                'region' => config('filesystems.disks.s3.region'),
                'credentials' => [
                    'key' => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);
        });
        
        $this->app->singleton(TextAnalysisServiceInterface::class, function ($app) {
            return new ComprehendService(
                $app->make(ComprehendClient::class),
                $app->make('log')
            );
        });
        
        // Document Processing Strategies
        $this->app->singleton(DocumentProcessorManager::class, function ($app) {
            $manager = new DocumentProcessorManager($app->make('log'));
            
            // Register processors in priority order
            $manager->register(new PdfDocumentProcessor());
            $manager->register(new ImageDocumentProcessor());
            $manager->register(new TextDocumentProcessor());
            
            return $manager;
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
