<?php

use App\Jobs\ProcessDocumentJob;
use App\Jobs\ProcessTextractJob;
use App\Models\Document;
use App\Models\DocumentProcessingJob;
use App\Models\User;
use App\ProcessingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('ProcessDocumentJob', function () {
    test('processes image document successfully', function () {
        Queue::fake(); // Prevent child jobs from running immediately
        
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'mime_type' => 'image/jpeg',
            'processing_status' => ProcessingStatus::PENDING,
        ]);
        
        $job = new ProcessDocumentJob($document->id);
        $job->handle(app('App\\Contracts\\Repositories\\DocumentRepositoryInterface'));
        
        // Check that processing jobs were created (only Textract for images)
        expect(DocumentProcessingJob::where('document_id', $document->id)->count())->toBe(1);
        
        // Check that the Textract job was dispatched
        Queue::assertPushed(ProcessTextractJob::class);
        
        // Check document status was updated to processing (child jobs haven't run)
        $document->refresh();
        expect($document->processing_status)->toBe(ProcessingStatus::PROCESSING);
    });
    
    test('handles non-existent document gracefully', function () {
        $job = new ProcessDocumentJob(999);
        $job->handle(app('App\\Contracts\\Repositories\\DocumentRepositoryInterface'));
        
        // Should not create any processing jobs
        expect(DocumentProcessingJob::count())->toBe(0);
    });
    
    test('handles unsupported document type', function () {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'mime_type' => 'application/unknown',
            'processing_status' => ProcessingStatus::PENDING,
        ]);
        
        $job = new ProcessDocumentJob($document->id);
        $job->handle(app('App\\Contracts\\Repositories\\DocumentRepositoryInterface'));
        
        // Should not create any processing jobs
        expect(DocumentProcessingJob::count())->toBe(0);
        
        // Check document status was marked as failed
        $document->refresh();
        expect($document->processing_status)->toBe(ProcessingStatus::FAILED);
    });
});
