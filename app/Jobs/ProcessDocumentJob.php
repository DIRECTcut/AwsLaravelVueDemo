<?php

namespace App\Jobs;

use App\Contracts\Repositories\DocumentRepositoryInterface;
use App\ProcessingStatus;
use App\Services\Processing\DocumentProcessorManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;

class ProcessDocumentJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

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
        DocumentRepositoryInterface $documentRepository,
        DocumentProcessorManager $processorManager,
        LoggerInterface $logger
    ): void {
        $document = $documentRepository->findById($this->documentId);

        if (! $document) {
            $logger->error('Document not found for processing', ['document_id' => $this->documentId]);

            return;
        }

        $logger->info('Starting document processing', [
            'document_id' => $document->id,
            'filename' => $document->original_filename,
            'type' => $document->getDocumentType()?->value,
        ]);

        // Update document status to processing
        $documentRepository->updateProcessingStatus($document->id, ProcessingStatus::PROCESSING);

        try {
            $jobs = $processorManager->processDocument($document);

            $this->dispatchJobs($jobs);

            $logger->info('Document processing jobs dispatched', [
                'document_id' => $document->id,
                'job_count' => count($jobs),
            ]);

        } catch (\Exception $e) {
            $logger->error('Error processing document', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $documentRepository->updateProcessingStatus($document->id, ProcessingStatus::FAILED);
            throw $e;
        }
    }

    private function dispatchJobs(array $jobs): void
    {
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
        app(LoggerInterface::class)->error('ProcessDocumentJob failed', [
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
