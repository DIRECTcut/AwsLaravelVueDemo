<?php

use App\Models\Document;
use App\Models\User;
use App\Services\Processing\DocumentProcessorManager;
use App\Services\Processing\ImageDocumentProcessor;
use App\Services\Processing\PdfDocumentProcessor;
use App\Services\Processing\TextDocumentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->manager = new DocumentProcessorManager();
    
    // Register processors
    $this->manager->register(new PdfDocumentProcessor());
    $this->manager->register(new ImageDocumentProcessor());
    $this->manager->register(new TextDocumentProcessor());
});

describe('DocumentProcessorManager', function () {
    test('registers processors and sorts by priority', function () {
        $processors = $this->manager->getProcessors();
        
        expect($processors)->toHaveCount(3);
        
        // Should be sorted by priority (PDF=20, Image=10, Text=5)
        expect($processors[0])->toBeInstanceOf(PdfDocumentProcessor::class);
        expect($processors[1])->toBeInstanceOf(ImageDocumentProcessor::class);
        expect($processors[2])->toBeInstanceOf(TextDocumentProcessor::class);
    });
    
    test('finds correct processor for PDF document', function () {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'mime_type' => 'application/pdf',
        ]);
        
        $processor = $this->manager->findProcessor($document);
        
        expect($processor)->toBeInstanceOf(PdfDocumentProcessor::class);
    });
    
    test('finds correct processor for image document', function () {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'mime_type' => 'image/jpeg',
        ]);
        
        $processor = $this->manager->findProcessor($document);
        
        expect($processor)->toBeInstanceOf(ImageDocumentProcessor::class);
    });
    
    test('finds correct processor for text document', function () {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'mime_type' => 'text/plain',
        ]);
        
        $processor = $this->manager->findProcessor($document);
        
        expect($processor)->toBeInstanceOf(TextDocumentProcessor::class);
    });
    
    test('returns null for unsupported document type', function () {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'mime_type' => 'application/unknown',
        ]);
        
        $processor = $this->manager->findProcessor($document);
        
        expect($processor)->toBeNull();
    });
    
    test('processes document using correct strategy', function () {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'mime_type' => 'image/png',
        ]);
        
        $jobs = $this->manager->processDocument($document);
        
        expect($jobs)->toBeArray();
        expect($jobs)->toHaveCount(1);
        expect($jobs[0]->job_type)->toBe('textract_text');
    });
    
    test('throws exception for unsupported document type during processing', function () {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'mime_type' => 'video/mp4',
        ]);
        
        expect(function () use ($document) {
            $this->manager->processDocument($document);
        })->toThrow(\RuntimeException::class, 'No processor available for document type: video/mp4');
    });
    
    test('gets all supported mime types', function () {
        $supportedTypes = $this->manager->getSupportedMimeTypes();
        
        expect($supportedTypes)->toBeArray();
        expect($supportedTypes)->toContain('application/pdf');
        expect($supportedTypes)->toContain('image/jpeg');
        expect($supportedTypes)->toContain('text/plain');
    });
    
    test('checks if mime type is supported', function () {
        expect($this->manager->isSupported('application/pdf'))->toBe(true);
        expect($this->manager->isSupported('image/jpeg'))->toBe(true);
        expect($this->manager->isSupported('text/plain'))->toBe(true);
        expect($this->manager->isSupported('video/mp4'))->toBe(false);
    });
    
    test('provides processor statistics', function () {
        $stats = $this->manager->getStatistics();
        
        expect($stats)->toHaveKeys(['total_processors', 'supported_mime_types', 'processors']);
        expect($stats['total_processors'])->toBe(3);
        expect($stats['supported_mime_types'])->toBeGreaterThan(0);
        expect($stats['processors'])->toHaveCount(3);
        
        // Check processor details
        expect($stats['processors'][0])->toHaveKeys(['class', 'priority', 'supported_types']);
        expect($stats['processors'][0]['class'])->toBe(PdfDocumentProcessor::class);
        expect($stats['processors'][0]['priority'])->toBe(20);
    });
});