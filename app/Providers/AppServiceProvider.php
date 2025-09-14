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
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $logger = $this->app->make(LoggerInterface::class);

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

        $this->app->singleton(DocumentAnalysisServiceInterface::class, function ($app) use ($logger) {
            return new TextractService(
                $app->make(TextractClient::class),
                $logger
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

        $this->app->singleton(TextAnalysisServiceInterface::class, function ($app) use ($logger) {
            return new ComprehendService(
                $app->make(ComprehendClient::class),
                $logger
            );
        });

        // Document Processing Strategies
        $this->app->singleton(DocumentProcessorManager::class, function ($app) use ($logger) {
            $manager = new DocumentProcessorManager($logger);

            $manager->register(new PdfDocumentProcessor($logger));
            $manager->register(new ImageDocumentProcessor($logger));
            $manager->register(new TextDocumentProcessor($logger));

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
