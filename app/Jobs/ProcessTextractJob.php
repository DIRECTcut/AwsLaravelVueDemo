<?php

namespace App\Jobs;

use App\Contracts\Aws\DocumentAnalysisServiceInterface;
use App\Models\DocumentAnalysisResult;
use App\Models\DocumentProcessingJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTextractJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $maxAttempts = 2;

    public function __construct(
        private int $processingJobId
    ) {}

    public function handle(
        DocumentAnalysisServiceInterface $textractService
    ): void {
        $processingJob = DocumentProcessingJob::find($this->processingJobId);
        
        if (!$processingJob) {
            Log::error("Processing job not found", ['job_id' => $this->processingJobId]);
            return;
        }

        $document = $processingJob->document;
        if (!$document) {
            Log::error("Document not found for processing job", ['job_id' => $this->processingJobId]);
            return;
        }

        Log::info("Starting Textract processing", [
            'job_id' => $processingJob->id,
            'document_id' => $document->id,
            'job_type' => $processingJob->job_type,
        ]);

        $processingJob->markAsStarted();

        try {
            // Determine analysis type based on job type
            $results = match($processingJob->job_type) {
                'textract_text' => $textractService->detectDocumentText(
                    $document->s3_key,
                    $document->s3_bucket
                ),
                'textract_analysis' => $textractService->analyzeDocument(
                    $document->s3_key,
                    $document->s3_bucket,
                    $processingJob->job_parameters['feature_types'] ?? ['FORMS', 'TABLES']
                ),
                default => throw new \InvalidArgumentException("Unknown Textract job type: {$processingJob->job_type}")
            };

            // Store results
            $metadata = [
                'processing_time' => now()->diffInSeconds($processingJob->started_at),
                'aws_request_id' => $results['ResponseMetadata']['RequestId'] ?? null,
            ];

            // Add partial processing info if present
            if (isset($results['IsPartial']) && $results['IsPartial']) {
                $metadata['is_partial'] = true;
                $metadata['partial_message'] = $results['StatusMessage'] ?? 'Some pages could not be processed';
                $metadata['warnings'] = $results['Warnings'] ?? [];
                
                Log::warning("Textract processing completed with partial results", [
                    'job_id' => $processingJob->id,
                    'document_id' => $document->id,
                    'message' => $metadata['partial_message'],
                ]);
            }

            DocumentAnalysisResult::create([
                'document_id' => $document->id,
                'analysis_type' => $processingJob->job_type,
                'raw_results' => $results,
                'processed_data' => $this->processTextractResults($results, $processingJob->job_type),
                'confidence_score' => $this->calculateAverageConfidence($results),
                'metadata' => $metadata,
            ]);

            $processingJob->markAsCompleted($results);

            Log::info("Textract processing completed", [
                'job_id' => $processingJob->id,
                'document_id' => $document->id,
            ]);

            // Check if all processing is complete
            $this->checkDocumentProcessingCompletion($document);

        } catch (\Exception $e) {
            Log::error("Textract processing failed", [
                'job_id' => $processingJob->id,
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $processingJob->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function processTextractResults(array $results, string $jobType): array
    {
        $processed = [
            'text_blocks' => [],
            'tables' => [],
            'forms' => [],
        ];

        foreach ($results['Blocks'] ?? [] as $block) {
            switch ($block['BlockType']) {
                case 'LINE':
                    $processed['text_blocks'][] = [
                        'text' => $block['Text'] ?? '',
                        'confidence' => $block['Confidence'] ?? 0,
                        'geometry' => $block['Geometry'] ?? null,
                    ];
                    break;
                    
                case 'TABLE':
                    $processed['tables'][] = [
                        'id' => $block['Id'],
                        'confidence' => $block['Confidence'] ?? 0,
                        'geometry' => $block['Geometry'] ?? null,
                    ];
                    break;
                    
                case 'KEY_VALUE_SET':
                    if (isset($block['EntityTypes']) && in_array('KEY', $block['EntityTypes'])) {
                        $processed['forms'][] = [
                            'type' => 'key',
                            'text' => $block['Text'] ?? '',
                            'confidence' => $block['Confidence'] ?? 0,
                        ];
                    }
                    break;
            }
        }

        return $processed;
    }

    private function calculateAverageConfidence(array $results): ?float
    {
        $confidences = [];
        
        foreach ($results['Blocks'] ?? [] as $block) {
            if (isset($block['Confidence'])) {
                $confidences[] = $block['Confidence'];
            }
        }
        
        return empty($confidences) ? null : array_sum($confidences) / count($confidences) / 100;
    }

    private function checkDocumentProcessingCompletion($document): void
    {
        $pendingJobs = $document->processingJobs()
            ->whereIn('status', [\App\JobStatus::PENDING, \App\JobStatus::PROCESSING])
            ->count();

        if ($pendingJobs === 0) {
            $document->update(['processing_status' => \App\ProcessingStatus::COMPLETED]);
            
            Log::info("Document processing completed", ['document_id' => $document->id]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessTextractJob failed", [
            'processing_job_id' => $this->processingJobId,
            'error' => $exception->getMessage(),
        ]);
    }
}
