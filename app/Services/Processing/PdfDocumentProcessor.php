<?php

namespace App\Services\Processing;

use App\Contracts\Processing\DocumentProcessorInterface;
use App\DocumentType;
use App\JobStatus;
use App\Models\Document;
use App\Models\DocumentProcessingJob;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PdfDocumentProcessor implements DocumentProcessorInterface
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger;
    }

    public function canProcess(Document $document): bool
    {
        $documentType = $document->getDocumentType();

        return $documentType === DocumentType::PDF;
    }

    public function process(Document $document): array
    {
        $this->logger->info('Processing PDF document', [
            'document_id' => $document->id,
            'file_size' => $document->file_size,
        ]);

        $jobs = [];

        // For PDFs larger than 5MB, use asynchronous processing
        $useAsync = $document->file_size > (5 * 1024 * 1024); // 5MB

        if ($useAsync) {
            // Large PDF - use asynchronous Textract analysis
            $jobs[] = DocumentProcessingJob::create([
                'document_id' => $document->id,
                'job_type' => 'textract_analysis',
                'status' => JobStatus::PENDING,
                'job_parameters' => [
                    'feature_types' => ['TABLES', 'FORMS'],
                    'async_processing' => true,
                ],
            ]);

            $this->logger->info('Scheduled async Textract analysis for large PDF', [
                'document_id' => $document->id,
                'file_size' => $document->file_size,
            ]);
        } else {
            // Small PDF - use synchronous processing
            $jobs[] = DocumentProcessingJob::create([
                'document_id' => $document->id,
                'job_type' => 'textract_analysis',
                'status' => JobStatus::PENDING,
                'job_parameters' => [
                    'feature_types' => ['TABLES', 'FORMS'],
                    'sync_processing' => true,
                ],
            ]);

            // For text-heavy PDFs, also schedule Comprehend analysis
            $jobs[] = DocumentProcessingJob::create([
                'document_id' => $document->id,
                'job_type' => 'comprehend_sentiment',
                'status' => JobStatus::PENDING,
            ]);

            $jobs[] = DocumentProcessingJob::create([
                'document_id' => $document->id,
                'job_type' => 'comprehend_entities',
                'status' => JobStatus::PENDING,
            ]);

            $this->logger->info('Scheduled sync processing for small PDF with Comprehend analysis', [
                'document_id' => $document->id,
                'job_count' => count($jobs),
            ]);
        }

        return $jobs;
    }

    public function getSupportedMimeTypes(): array
    {
        return [
            'application/pdf',
        ];
    }

    public function getPriority(): int
    {
        return 20; // Higher priority than text files (more complex processing)
    }
}
