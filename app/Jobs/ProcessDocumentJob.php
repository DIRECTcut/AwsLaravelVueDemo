<?php

namespace App\Jobs;

use App\Contracts\Repositories\DocumentRepositoryInterface;
use App\DocumentType;
use App\Models\Document;
use App\Models\DocumentProcessingJob;
use App\ProcessingStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDocumentJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $maxAttempts = 3;

    public function __construct(
        private int $documentId
    ) {}

    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    public function handle(
        DocumentRepositoryInterface $documentRepository
    ): void {
        $document = $documentRepository->findById($this->documentId);
        
        if (!$document) {
            Log::error("Document not found for processing", ['document_id' => $this->documentId]);
            return;
        }

        Log::info("Starting document processing", [
            'document_id' => $document->id,
            'filename' => $document->original_filename,
            'type' => $document->getDocumentType()?->value,
        ]);

        // Update document status to processing
        $documentRepository->updateProcessingStatus($document->id, ProcessingStatus::PROCESSING);

        try {
            $documentType = $document->getDocumentType();
            
            if (!$documentType) {
                Log::warning("Unsupported document type", [
                    'document_id' => $document->id,
                    'mime_type' => $document->mime_type,
                ]);
                $documentRepository->updateProcessingStatus($document->id, ProcessingStatus::FAILED);
                return;
            }

            // Dispatch appropriate processing jobs based on document type
            $this->dispatchProcessingJobs($document, $documentType);

            Log::info("Document processing jobs dispatched", ['document_id' => $document->id]);
            
        } catch (\Exception $e) {
            Log::error("Error processing document", [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $documentRepository->updateProcessingStatus($document->id, ProcessingStatus::FAILED);
            throw $e;
        }
    }

    private function dispatchProcessingJobs(Document $document, DocumentType $documentType): void
    {
        // Create processing job records
        $jobs = [];

        // Textract jobs for supported document types
        if ($documentType->supportedByTextract()) {
            $jobs[] = DocumentProcessingJob::create([
                'document_id' => $document->id,
                'job_type' => 'textract_text',
                'status' => \App\JobStatus::PENDING,
                'job_parameters' => [
                    'feature_types' => ['TABLES', 'FORMS'],
                ],
            ]);
        }

        // Comprehend jobs for text-based documents
        if ($documentType->supportedByComprehend()) {
            $jobs[] = DocumentProcessingJob::create([
                'document_id' => $document->id,
                'job_type' => 'comprehend_sentiment',
                'status' => \App\JobStatus::PENDING,
            ]);

            $jobs[] = DocumentProcessingJob::create([
                'document_id' => $document->id,
                'job_type' => 'comprehend_entities',
                'status' => \App\JobStatus::PENDING,
            ]);
        }

        // Dispatch the actual processing jobs
        foreach ($jobs as $job) {
            if (str_starts_with($job->job_type, 'textract_')) {
                ProcessTextractJob::dispatch($job->id);
            } elseif (str_starts_with($job->job_type, 'comprehend_')) {
                ProcessComprehendJob::dispatch($job->id);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessDocumentJob failed", [
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
