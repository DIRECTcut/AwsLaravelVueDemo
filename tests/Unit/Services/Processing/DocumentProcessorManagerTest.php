<?php

use App\Contracts\Processing\DocumentProcessorInterface;
use App\Services\Processing\DocumentProcessorManager;

beforeEach(function () {
    $this->manager = new DocumentProcessorManager;
});

describe('DocumentProcessorManager', function () {
    describe('getSupportedMimeTypes', function () {
        test('returns empty array when no processors registered', function () {
            $mimeTypes = $this->manager->getSupportedMimeTypes();

            expect($mimeTypes)->toBeArray();
            expect($mimeTypes)->toBeEmpty();
        });

        test('returns unique sorted mime types from all processors', function () {
            // Create mock processors with overlapping mime types
            $processor1 = Mockery::mock(DocumentProcessorInterface::class);
            $processor1->shouldReceive('getSupportedMimeTypes')
                ->andReturn(['application/pdf', 'image/jpeg', 'text/plain']);
            $processor1->shouldReceive('getPriority')->andReturn(10);

            $processor2 = Mockery::mock(DocumentProcessorInterface::class);
            $processor2->shouldReceive('getSupportedMimeTypes')
                ->andReturn(['image/jpeg', 'image/png', 'text/csv']); // image/jpeg is duplicate
            $processor2->shouldReceive('getPriority')->andReturn(5);

            $processor3 = Mockery::mock(DocumentProcessorInterface::class);
            $processor3->shouldReceive('getSupportedMimeTypes')
                ->andReturn(['application/json', 'application/pdf']); // application/pdf is duplicate
            $processor3->shouldReceive('getPriority')->andReturn(15);

            // Register processors
            $this->manager->register($processor1);
            $this->manager->register($processor2);
            $this->manager->register($processor3);

            $mimeTypes = $this->manager->getSupportedMimeTypes();

            // Should be unique and sorted
            $expected = [
                'application/json',
                'application/pdf',
                'image/jpeg',
                'image/png',
                'text/csv',
                'text/plain',
            ];

            expect($mimeTypes)->toEqual($expected);
            expect($mimeTypes)->toHaveCount(6); // No duplicates
        });

        test('handles processor with empty mime types', function () {
            $processor1 = Mockery::mock(DocumentProcessorInterface::class);
            $processor1->shouldReceive('getSupportedMimeTypes')
                ->andReturn(['application/pdf']);
            $processor1->shouldReceive('getPriority')->andReturn(10);

            $processor2 = Mockery::mock(DocumentProcessorInterface::class);
            $processor2->shouldReceive('getSupportedMimeTypes')
                ->andReturn([]); // Empty array
            $processor2->shouldReceive('getPriority')->andReturn(5);

            $this->manager->register($processor1);
            $this->manager->register($processor2);

            $mimeTypes = $this->manager->getSupportedMimeTypes();

            expect($mimeTypes)->toEqual(['application/pdf']);
        });

        test('handles single processor', function () {
            $processor = Mockery::mock(DocumentProcessorInterface::class);
            $processor->shouldReceive('getSupportedMimeTypes')
                ->andReturn(['text/plain', 'text/csv', 'application/json']);
            $processor->shouldReceive('getPriority')->andReturn(10);

            $this->manager->register($processor);

            $mimeTypes = $this->manager->getSupportedMimeTypes();

            expect($mimeTypes)->toEqual(['application/json', 'text/csv', 'text/plain']);
        });

        test('returns values not keys when flattening', function () {
            $processor = Mockery::mock(DocumentProcessorInterface::class);
            $processor->shouldReceive('getSupportedMimeTypes')
                ->andReturn([
                    0 => 'text/plain',
                    2 => 'application/pdf', // Non-sequential keys
                    5 => 'image/jpeg',
                ]);
            $processor->shouldReceive('getPriority')->andReturn(10);

            $this->manager->register($processor);

            $mimeTypes = $this->manager->getSupportedMimeTypes();

            // Should be re-indexed with values()
            expect($mimeTypes)->toEqual([
                0 => 'application/pdf',
                1 => 'image/jpeg',
                2 => 'text/plain',
            ]);

            // Ensure keys are sequential
            expect(array_keys($mimeTypes))->toEqual([0, 1, 2]);
        });

        test('maintains alphabetical order', function () {
            $processor = Mockery::mock(DocumentProcessorInterface::class);
            $processor->shouldReceive('getSupportedMimeTypes')
                ->andReturn([
                    'text/xml',
                    'application/pdf',
                    'image/png',
                    'application/json',
                    'text/plain',
                    'image/jpeg',
                ]);
            $processor->shouldReceive('getPriority')->andReturn(10);

            $this->manager->register($processor);

            $mimeTypes = $this->manager->getSupportedMimeTypes();

            // Should be alphabetically sorted
            expect($mimeTypes)->toEqual([
                'application/json',
                'application/pdf',
                'image/jpeg',
                'image/png',
                'text/plain',
                'text/xml',
            ]);
        });
    });
});

afterEach(function () {
    Mockery::close();
});
