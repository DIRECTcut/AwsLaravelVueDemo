<?php

use App\Models\Document;
use App\Models\User;
use App\Services\Processing\ImageDocumentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->processor = new ImageDocumentProcessor;
    $this->user = User::factory()->create();
});

describe('ImageDocumentProcessor', function () {
    test('can process image documents', function () {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'mime_type' => 'image/jpeg',
        ]);

        expect($this->processor->canProcess($document))->toBe(true);
    });

    test('cannot process non-image documents', function () {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'mime_type' => 'application/pdf',
        ]);

        expect($this->processor->canProcess($document))->toBe(false);
    });

    test('processes image document with textract job', function () {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'mime_type' => 'image/png',
        ]);

        $jobs = $this->processor->process($document);

        expect($jobs)->toHaveCount(1);
        expect($jobs[0]->job_type)->toBe('textract_text');
        expect($jobs[0]->job_parameters['sync_processing'])->toBe(true);
    });

    test('supports all image mime types', function () {
        $supportedTypes = $this->processor->getSupportedMimeTypes();

        expect($supportedTypes)->toContain('image/jpeg');
        expect($supportedTypes)->toContain('image/png');
        expect($supportedTypes)->toContain('image/tiff');
        expect($supportedTypes)->toContain('image/bmp');
    });

    test('has correct priority', function () {
        expect($this->processor->getPriority())->toBe(10);
    });
});
