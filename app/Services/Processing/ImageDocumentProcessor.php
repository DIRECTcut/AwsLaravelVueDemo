<?php

namespace App\Services\Processing;

use App\Contracts\Processing\DocumentProcessorInterface;
use App\DocumentType;
use App\JobStatus;
use App\Models\Document;
use App\Models\DocumentProcessingJob;
use Illuminate\Support\Facades\Log;

class ImageDocumentProcessor implements DocumentProcessorInterface
{
    public function canProcess(Document $document): bool
    {
        $documentType = $document->getDocumentType();
        return $documentType === DocumentType::IMAGE;
    }

    public function process(Document $document): array
    {
        Log::info('Processing image document', [
            'document_id' => $document->id,
            'mime_type' => $document->mime_type,
        ]);

        $jobs = [];

        // Images only support Textract text detection (not full analysis)
        $jobs[] = DocumentProcessingJob::create([
            'document_id' => $document->id,
            'job_type' => 'textract_text',
            'status' => JobStatus::PENDING,
            'job_parameters' => [
                'sync_processing' => true, // Images can use synchronous processing
            ],
        ]);

        // If OCR extracts text, we can run Comprehend on it
        // But we'll let the Textract job handle scheduling Comprehend based on results
        
        Log::info('Created processing jobs for image document', [
            'document_id' => $document->id,
            'job_count' => count($jobs),
        ]);

        return $jobs;
    }

    public function getSupportedMimeTypes(): array
    {
        return [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/tiff',
            'image/tif',
            'image/bmp',
            'image/webp',
        ];
    }

    public function getPriority(): int
    {
        return 10; // High priority for images (fast processing)
    }
}