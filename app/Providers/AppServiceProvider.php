<?php

namespace App\Providers;

use App\Contracts\Aws\DocumentAnalysisServiceInterface;
use App\Contracts\Aws\StorageServiceInterface;
use App\Contracts\Aws\TextAnalysisServiceInterface;
use App\Contracts\Repositories\DocumentRepositoryInterface;
use App\Repositories\DocumentRepository;
use App\Services\Aws\ComprehendService;
use App\Services\Aws\FakeComprehendService;
use App\Services\Aws\FakeTextractService;
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
            $config = [
                'version' => 'latest',
                'region' => config('filesystems.disks.s3.region'),
                'credentials' => [
                    'key' => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ];

            // Add MinIO/custom endpoint support
            if ($endpoint = config('filesystems.disks.s3.endpoint')) {
                $config['endpoint'] = $endpoint;
            }

            if (config('filesystems.disks.s3.use_path_style_endpoint')) {
                $config['use_path_style_endpoint'] = true;
            }

            return new S3Client($config);
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
            if (env('AWS_TEXTRACT_USE_FAKE', false)) {
                return new FakeTextractService($logger);
            }

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
            if (env('AWS_COMPREHEND_USE_FAKE', false)) {
                return new FakeComprehendService($logger);
            }

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
        // Validate AWS/S3 configuration on startup
        $this->validateAwsConfiguration();
    }

    private function validateAwsConfiguration(): void
    {
        $required = [
            'filesystems.disks.s3.key' => 'AWS_ACCESS_KEY_ID',
            'filesystems.disks.s3.secret' => 'AWS_SECRET_ACCESS_KEY',
            'filesystems.disks.s3.bucket' => 'AWS_BUCKET',
            'filesystems.disks.s3.region' => 'AWS_DEFAULT_REGION',
        ];

        $missing = [];
        foreach ($required as $config => $env) {
            if (empty(config($config))) {
                $missing[] = $env;
            }
        }

        if (! empty($missing)) {
            $message = 'Missing required AWS configuration: '.implode(', ', $missing);
            logger()->error($message);

            if (app()->environment('production')) {
                throw new \RuntimeException($message);
            }

            return; // Skip connection test if config is missing
        }

        $endpoint = config('filesystems.disks.s3.endpoint');
        if ($endpoint) {
            // logger()->info('Using custom S3 endpoint', ['endpoint' => $endpoint]);
        } else {
            logger()->info('Using default AWS S3 endpoint');
        }

        // Test actual S3 connection
        $this->testS3Connection();

        // Log if using fake services
        if (env('AWS_TEXTRACT_USE_FAKE', false)) {
            // logger()->warning('Using FAKE Textract service - not connecting to real AWS Textract');
        }

        if (env('AWS_COMPREHEND_USE_FAKE', false)) {
            // logger()->warning('Using FAKE Comprehend service - not connecting to real AWS Comprehend');
        }
    }

    private function testS3Connection(): void
    {
        try {
            $s3Client = app(S3Client::class);
            $bucket = config('filesystems.disks.s3.bucket');

            // check if bucket exists
            $s3Client->headBucket(['Bucket' => $bucket]);

        } catch (\Aws\S3\Exception\S3Exception $e) {
            $errorCode = $e->getAwsErrorCode();
            $message = match ($errorCode) {
                'InvalidAccessKeyId' => 'Invalid AWS access key',
                'SignatureDoesNotMatch' => 'Invalid AWS secret key',
                'NoSuchBucket' => "Bucket '{$bucket}' does not exist",
                'Forbidden', 'AccessDenied' => 'Access denied to bucket',
                default => 'S3 connection failed: '.$e->getMessage()
            };

            logger()->error('S3 configuration error', [
                'error' => $message,
                'code' => $errorCode,
                'bucket' => $bucket,
            ]);

            if (app()->environment('production')) {
                throw new \RuntimeException($message);
            }

        } catch (\Exception $e) {
            logger()->error('S3 connection test failed', [
                'error' => $e->getMessage(),
                'bucket' => config('filesystems.disks.s3.bucket'),
            ]);

            if (app()->environment('production')) {
                throw new \RuntimeException('Failed to connect to S3: '.$e->getMessage());
            }
        }
    }
}
