<?php

namespace App\Jobs;

use App\Contracts\Aws\TextAnalysisServiceInterface;
use App\Models\DocumentAnalysisResult;
use App\Models\DocumentProcessingJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;

class ProcessComprehendJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes

    public int $maxAttempts = 2;

    public function __construct(
        private int $processingJobId
    ) {}

    public function handle(
        TextAnalysisServiceInterface $comprehendService,
        LoggerInterface $logger
    ): void {
        $processingJob = DocumentProcessingJob::find($this->processingJobId);

        if (! $processingJob) {
            $logger->error('Processing job not found', ['job_id' => $this->processingJobId]);

            return;
        }

        $document = $processingJob->document;
        if (! $document) {
            $logger->error('Document not found for processing job', ['job_id' => $this->processingJobId]);

            return;
        }

        $logger->info('Starting Comprehend processing', [
            'job_id' => $processingJob->id,
            'document_id' => $document->id,
            'job_type' => $processingJob->job_type,
        ]);

        $processingJob->markAsStarted();

        try {
            // Get document text (from Textract results or direct text)
            $documentText = $this->extractDocumentText($document);

            if (empty($documentText)) {
                throw new \RuntimeException('No text available for Comprehend analysis');
            }

            // Determine analysis type and process
            $results = match ($processingJob->job_type) {
                'comprehend_sentiment' => $comprehendService->detectSentiment($documentText),
                'comprehend_entities' => $comprehendService->detectEntities($documentText),
                'comprehend_key_phrases' => $comprehendService->detectKeyPhrases($documentText),
                'comprehend_language' => $comprehendService->detectLanguage($documentText),
                default => throw new \InvalidArgumentException("Unknown Comprehend job type: {$processingJob->job_type}")
            };

            // Store results
            DocumentAnalysisResult::create([
                'document_id' => $document->id,
                'analysis_type' => $processingJob->job_type,
                'raw_results' => $results,
                'processed_data' => $this->processComprehendResults($results, $processingJob->job_type),
                'confidence_score' => $this->extractConfidenceScore($results, $processingJob->job_type),
                'metadata' => [
                    'processing_time' => now()->diffInSeconds($processingJob->started_at),
                    'text_length' => strlen($documentText),
                    'aws_request_id' => $results['ResponseMetadata']['RequestId'] ?? null,
                ],
            ]);

            $processingJob->markAsCompleted($results);

            $logger->info('Comprehend processing completed', [
                'job_id' => $processingJob->id,
                'document_id' => $document->id,
            ]);

            // Check if all processing is complete
            $this->checkDocumentProcessingCompletion($document, $logger);

        } catch (\Exception $e) {
            $logger->error('Comprehend processing failed', [
                'job_id' => $processingJob->id,
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $processingJob->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function extractDocumentText($document): string
    {
        // Try to get text from Textract results first
        $textractResult = $document->analysisResults()
            ->where('analysis_type', 'textract_text')
            ->first();

        if ($textractResult && isset($textractResult->processed_data['text_blocks'])) {
            $texts = array_column($textractResult->processed_data['text_blocks'], 'text');

            return implode(' ', $texts);
        }

        // Fallback: if it's a text document, we could read it directly
        // For now, return empty string if no Textract results available
        return '';
    }

    private function processComprehendResults(array $results, string $jobType): array
    {
        return match ($jobType) {
            'comprehend_sentiment' => [
                'sentiment' => $results['Sentiment'] ?? null,
                'confidence_scores' => $results['SentimentScore'] ?? [],
            ],
            'comprehend_entities' => [
                'entities' => array_map(fn ($entity) => [
                    'text' => $entity['Text'] ?? '',
                    'type' => $entity['Type'] ?? '',
                    'confidence' => $entity['Score'] ?? 0,
                ], $results['Entities'] ?? []),
            ],
            'comprehend_key_phrases' => [
                'key_phrases' => array_map(fn ($phrase) => [
                    'text' => $phrase['Text'] ?? '',
                    'confidence' => $phrase['Score'] ?? 0,
                ], $results['KeyPhrases'] ?? []),
            ],
            'comprehend_language' => [
                'languages' => array_map(fn ($lang) => [
                    'code' => $lang['LanguageCode'] ?? '',
                    'confidence' => $lang['Score'] ?? 0,
                ], $results['Languages'] ?? []),
            ],
            default => $results,
        };
    }

    private function extractConfidenceScore(array $results, string $jobType): ?float
    {
        return match ($jobType) {
            'comprehend_sentiment' => $this->getMaxConfidenceFromSentimentScore($results['SentimentScore'] ?? []),
            'comprehend_entities' => $this->getAverageEntityConfidence($results['Entities'] ?? []),
            'comprehend_key_phrases' => $this->getAverageKeyPhraseConfidence($results['KeyPhrases'] ?? []),
            'comprehend_language' => $this->getMaxLanguageConfidence($results['Languages'] ?? []),
            default => null,
        };
    }

    private function getMaxConfidenceFromSentimentScore(array $sentimentScore): ?float
    {
        if (empty($sentimentScore)) {
            return null;
        }

        return max(array_values($sentimentScore));
    }

    private function getAverageEntityConfidence(array $entities): ?float
    {
        if (empty($entities)) {
            return null;
        }
        $scores = array_column($entities, 'Score');

        return array_sum($scores) / count($scores);
    }

    private function getAverageKeyPhraseConfidence(array $keyPhrases): ?float
    {
        if (empty($keyPhrases)) {
            return null;
        }
        $scores = array_column($keyPhrases, 'Score');

        return array_sum($scores) / count($scores);
    }

    private function getMaxLanguageConfidence(array $languages): ?float
    {
        if (empty($languages)) {
            return null;
        }
        $scores = array_column($languages, 'Score');

        return max($scores);
    }

    private function checkDocumentProcessingCompletion($document, LoggerInterface $logger): void
    {
        $pendingJobs = $document->processingJobs()
            ->whereIn('status', [\App\JobStatus::PENDING, \App\JobStatus::PROCESSING])
            ->count();

        if ($pendingJobs === 0) {
            $document->update(['processing_status' => \App\ProcessingStatus::COMPLETED]);

            $logger->info('Document processing completed', ['document_id' => $document->id]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        app(LoggerInterface::class)->error('ProcessComprehendJob failed', [
            'processing_job_id' => $this->processingJobId,
            'error' => $exception->getMessage(),
        ]);
    }
}
