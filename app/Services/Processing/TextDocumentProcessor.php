<?php

namespace App\Services\Processing;

use App\Contracts\Processing\DocumentProcessorInterface;
use App\DocumentType;
use App\JobStatus;
use App\Models\Document;
use App\Models\DocumentProcessingJob;
use Illuminate\Support\Facades\Log;

class TextDocumentProcessor implements DocumentProcessorInterface
{
    public function canProcess(Document $document): bool
    {
        $documentType = $document->getDocumentType();
        return $documentType === DocumentType::TEXT;
    }

    public function process(Document $document): array
    {
        Log::info('Processing text document', [
            'document_id' => $document->id,
            'mime_type' => $document->mime_type,
        ]);

        $jobs = [];

        // Text documents don't need Textract - go straight to Comprehend
        $jobs[] = DocumentProcessingJob::create([
            'document_id' => $document->id,
            'job_type' => 'comprehend_sentiment',
            'status' => JobStatus::PENDING,
            'job_parameters' => [
                'direct_text_processing' => true, // Skip Textract, process text directly
            ],
        ]);

        $jobs[] = DocumentProcessingJob::create([
            'document_id' => $document->id,
            'job_type' => 'comprehend_entities',
            'status' => JobStatus::PENDING,
            'job_parameters' => [
                'direct_text_processing' => true,
            ],
        ]);

        $jobs[] = DocumentProcessingJob::create([
            'document_id' => $document->id,
            'job_type' => 'comprehend_key_phrases',
            'status' => JobStatus::PENDING,
            'job_parameters' => [
                'direct_text_processing' => true,
            ],
        ]);

        $jobs[] = DocumentProcessingJob::create([
            'document_id' => $document->id,
            'job_type' => 'comprehend_language',
            'status' => JobStatus::PENDING,
            'job_parameters' => [
                'direct_text_processing' => true,
            ],
        ]);

        Log::info('Created Comprehend processing jobs for text document', [
            'document_id' => $document->id,
            'job_count' => count($jobs),
        ]);

        return $jobs;
    }

    public function getSupportedMimeTypes(): array
    {
        return [
            'text/plain',
            'text/csv',
            'text/tab-separated-values',
            'application/json',
            'application/xml',
            'text/xml',
            'text/markdown',
            'text/x-markdown',
        ];
    }

    public function getPriority(): int
    {
        return 5; // Lower priority (simple processing)
    }
}