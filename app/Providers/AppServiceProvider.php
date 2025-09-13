<?php

namespace App\Providers;

use App\Contracts\Aws\DocumentAnalysisServiceInterface;
use App\Contracts\Aws\StorageServiceInterface;
use App\Contracts\Aws\TextAnalysisServiceInterface;
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
        
        // AWS service bindings (placeholder implementations)
        $this->app->singleton(DocumentAnalysisServiceInterface::class, function () {
            // TODO: Implement actual Textract service
            return new class implements DocumentAnalysisServiceInterface {
                public function startDocumentTextDetection(string $s3Key, string $s3Bucket): string { return 'job-123'; }
                public function getDocumentTextDetectionResults(string $jobId): ?array { return null; }
                public function detectDocumentText(string $s3Key, string $s3Bucket): array { return ['Blocks' => []]; }
                public function analyzeDocument(string $s3Key, string $s3Bucket, array $featureTypes = ['FORMS', 'TABLES']): array { return ['Blocks' => []]; }
                public function startDocumentAnalysis(string $s3Key, string $s3Bucket, array $featureTypes = ['FORMS', 'TABLES']): string { return 'job-456'; }
                public function getDocumentAnalysisResults(string $jobId): ?array { return null; }
            };
        });
        
        $this->app->singleton(TextAnalysisServiceInterface::class, function () {
            // TODO: Implement actual Comprehend service
            return new class implements TextAnalysisServiceInterface {
                public function detectSentiment(string $text, string $languageCode = 'en'): array { return ['Sentiment' => 'NEUTRAL', 'SentimentScore' => ['Neutral' => 0.9]]; }
                public function detectEntities(string $text, string $languageCode = 'en'): array { return ['Entities' => []]; }
                public function detectKeyPhrases(string $text, string $languageCode = 'en'): array { return ['KeyPhrases' => []]; }
                public function detectLanguage(string $text): array { return ['Languages' => [['LanguageCode' => 'en', 'Score' => 0.99]]]; }
                public function startEntitiesDetectionJob(array $inputDataConfig, array $outputDataConfig, string $dataAccessRoleArn, string $languageCode = 'en'): string { return 'job-789'; }
                public function startSentimentDetectionJob(array $inputDataConfig, array $outputDataConfig, string $dataAccessRoleArn, string $languageCode = 'en'): string { return 'job-101'; }
                public function describeEntitiesDetectionJob(string $jobId): array { return ['JobStatus' => 'COMPLETED']; }
                public function describeSentimentDetectionJob(string $jobId): array { return ['JobStatus' => 'COMPLETED']; }
            };
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
