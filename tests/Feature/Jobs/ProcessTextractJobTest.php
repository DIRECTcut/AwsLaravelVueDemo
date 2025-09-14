<?php

use App\Contracts\Aws\DocumentAnalysisServiceInterface;
use App\Jobs\ProcessTextractJob;
use App\JobStatus;
use App\Models\Document;
use App\Models\DocumentAnalysisResult;
use App\Models\DocumentProcessingJob;
use App\Models\User;
use App\ProcessingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->document = Document::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => ProcessingStatus::PROCESSING,
    ]);

    $this->textractService = Mockery::mock(DocumentAnalysisServiceInterface::class);
    $this->app->instance(DocumentAnalysisServiceInterface::class, $this->textractService);
});

afterEach(function () {
    Mockery::close();
});

describe('ProcessTextractJob', function () {
    test('processes textract_text job successfully', function () {
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'textract_text',
            'status' => JobStatus::PENDING,
        ]);

        $mockResults = [
            'Blocks' => [
                [
                    'BlockType' => 'LINE',
                    'Text' => 'Sample text line',
                    'Confidence' => 95.5,
                    'Geometry' => ['test' => 'geometry'],
                ],
                [
                    'BlockType' => 'WORD',
                    'Text' => 'Sample',
                    'Confidence' => 97.2,
                ],
            ],
            'ResponseMetadata' => ['RequestId' => 'test-request-123'],
        ];

        $this->textractService->shouldReceive('detectDocumentText')
            ->once()
            ->with($this->document->s3_key, $this->document->s3_bucket)
            ->andReturn($mockResults);

        $job = new ProcessTextractJob($processingJob->id);
        $job->handle($this->textractService, app('Psr\\Log\\LoggerInterface'));

        // Check processing job was updated
        $processingJob->refresh();
        expect($processingJob->status)->toBe(JobStatus::COMPLETED);
        expect($processingJob->started_at)->not->toBeNull();
        expect($processingJob->completed_at)->not->toBeNull();
        expect($processingJob->result_data)->toEqual($mockResults);

        // Check analysis result was created
        expect(DocumentAnalysisResult::where('document_id', $this->document->id)->count())->toBe(1);

        $result = DocumentAnalysisResult::where('document_id', $this->document->id)->first();
        expect($result->analysis_type)->toBe('textract_text');
        expect($result->raw_results)->toEqual($mockResults);
        expect($result->confidence_score)->toBeGreaterThan(0);

        // Check processed data structure
        $processedData = $result->processed_data;
        expect($processedData)->toHaveKeys(['text_blocks', 'tables', 'forms']);
        expect($processedData['text_blocks'])->toHaveCount(1);
        expect($processedData['text_blocks'][0]['text'])->toBe('Sample text line');
        expect($processedData['text_blocks'][0]['confidence'])->toBe(95.5);
    });

    test('processes textract_analysis job successfully', function () {
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'textract_analysis',
            'status' => JobStatus::PENDING,
            'job_parameters' => ['feature_types' => ['FORMS', 'TABLES']],
        ]);

        $mockResults = [
            'Blocks' => [
                [
                    'BlockType' => 'TABLE',
                    'Id' => 'table-123',
                    'Confidence' => 92.1,
                    'Geometry' => ['test' => 'table-geometry'],
                ],
                [
                    'BlockType' => 'KEY_VALUE_SET',
                    'EntityTypes' => ['KEY'],
                    'Text' => 'Name:',
                    'Confidence' => 88.7,
                ],
            ],
            'ResponseMetadata' => ['RequestId' => 'test-request-456'],
        ];

        $this->textractService->shouldReceive('analyzeDocument')
            ->once()
            ->with(
                $this->document->s3_key,
                $this->document->s3_bucket,
                ['FORMS', 'TABLES']
            )
            ->andReturn($mockResults);

        $job = new ProcessTextractJob($processingJob->id);
        $job->handle($this->textractService, app('Psr\\Log\\LoggerInterface'));

        // Check processing job was updated
        $processingJob->refresh();
        expect($processingJob->status)->toBe(JobStatus::COMPLETED);

        // Check analysis result was created with correct structure
        $result = DocumentAnalysisResult::where('document_id', $this->document->id)->first();
        expect($result->analysis_type)->toBe('textract_analysis');

        $processedData = $result->processed_data;
        expect($processedData['tables'])->toHaveCount(1);
        expect($processedData['forms'])->toHaveCount(1);
        expect($processedData['tables'][0]['id'])->toBe('table-123');
        expect($processedData['forms'][0]['type'])->toBe('key');
        expect($processedData['forms'][0]['text'])->toBe('Name:');
    });

    test('handles non-existent processing job gracefully', function () {
        $job = new ProcessTextractJob(999);
        $job->handle($this->textractService, app('Psr\\Log\\LoggerInterface'));

        // Should not create any analysis results
        expect(DocumentAnalysisResult::count())->toBe(0);
    });

    test('handles Textract service errors', function () {
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'textract_text',
            'status' => JobStatus::PENDING,
        ]);

        $this->textractService->shouldReceive('detectDocumentText')
            ->once()
            ->andThrow(new \Exception('Textract API error'));

        expect(function () use ($processingJob) {
            $job = new ProcessTextractJob($processingJob->id);
            $job->handle($this->textractService, app('Psr\\Log\\LoggerInterface'));
        })->toThrow(\Exception::class);

        // Check processing job was marked as failed
        $processingJob->refresh();
        expect($processingJob->status)->toBe(JobStatus::FAILED);
        expect($processingJob->error_message)->toContain('Textract API error');
    });

    test('throws exception for unknown job type', function () {
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'unknown_textract_type',
            'status' => JobStatus::PENDING,
        ]);

        expect(function () use ($processingJob) {
            $job = new ProcessTextractJob($processingJob->id);
            $job->handle($this->textractService, app('Psr\\Log\\LoggerInterface'));
        })->toThrow(\InvalidArgumentException::class);
    });

    test('calculates average confidence correctly', function () {
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'textract_text',
            'status' => JobStatus::PENDING,
        ]);

        $mockResults = [
            'Blocks' => [
                ['BlockType' => 'LINE', 'Confidence' => 90],
                ['BlockType' => 'LINE', 'Confidence' => 80],
                ['BlockType' => 'WORD'], // No confidence
                ['BlockType' => 'LINE', 'Confidence' => 70],
            ],
            'ResponseMetadata' => ['RequestId' => 'test-request'],
        ];

        $this->textractService->shouldReceive('detectDocumentText')
            ->once()
            ->andReturn($mockResults);

        $job = new ProcessTextractJob($processingJob->id);
        $job->handle($this->textractService, app('Psr\\Log\\LoggerInterface'));

        $result = DocumentAnalysisResult::where('document_id', $this->document->id)->first();

        // Average of 90, 80, 70 = 80, divided by 100 = 0.8
        expect($result->confidence_score)->toEqual(0.8);
    });

    test('marks document as completed when all jobs finished', function () {
        // Create another processing job that's already completed
        DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'comprehend_sentiment',
            'status' => JobStatus::COMPLETED,
        ]);

        // Create the current job (last pending one)
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'textract_text',
            'status' => JobStatus::PENDING,
        ]);

        $this->textractService->shouldReceive('detectDocumentText')
            ->once()
            ->andReturn(['Blocks' => [], 'ResponseMetadata' => []]);

        $job = new ProcessTextractJob($processingJob->id);
        $job->handle($this->textractService, app('Psr\\Log\\LoggerInterface'));

        // Document should be marked as completed since all jobs are done
        $this->document->refresh();
        expect($this->document->processing_status)->toBe(ProcessingStatus::COMPLETED);
    });

    test('does not mark document as completed when jobs still pending', function () {
        // Create another processing job that's still pending
        DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'comprehend_sentiment',
            'status' => JobStatus::PENDING,
        ]);

        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'textract_text',
            'status' => JobStatus::PENDING,
        ]);

        $this->textractService->shouldReceive('detectDocumentText')
            ->once()
            ->andReturn(['Blocks' => [], 'ResponseMetadata' => []]);

        $job = new ProcessTextractJob($processingJob->id);
        $job->handle($this->textractService, app('Psr\\Log\\LoggerInterface'));

        // Document should still be processing since other jobs are pending
        $this->document->refresh();
        expect($this->document->processing_status)->toBe(ProcessingStatus::PROCESSING);
    });
});
