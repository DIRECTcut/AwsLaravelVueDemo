<?php

use App\Contracts\Aws\TextAnalysisServiceInterface;
use App\Jobs\ProcessComprehendJob;
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
    
    $this->comprehendService = Mockery::mock(TextAnalysisServiceInterface::class);
    $this->app->instance(TextAnalysisServiceInterface::class, $this->comprehendService);
});

afterEach(function () {
    Mockery::close();
});

describe('ProcessComprehendJob', function () {
    test('processes sentiment analysis successfully', function () {
        // Create Textract result to provide text for analysis
        DocumentAnalysisResult::factory()->create([
            'document_id' => $this->document->id,
            'analysis_type' => 'textract_text',
            'processed_data' => [
                'text_blocks' => [
                    ['text' => 'This is a great document.'],
                    ['text' => 'I really enjoyed reading it.'],
                ]
            ],
        ]);
        
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'comprehend_sentiment',
            'status' => JobStatus::PENDING,
        ]);
        
        $mockResults = [
            'Sentiment' => 'POSITIVE',
            'SentimentScore' => [
                'Positive' => 0.85,
                'Negative' => 0.10,
                'Neutral' => 0.04,
                'Mixed' => 0.01,
            ],
            'ResponseMetadata' => ['RequestId' => 'sentiment-123']
        ];
        
        $this->comprehendService->shouldReceive('detectSentiment')
            ->once()
            ->with('This is a great document. I really enjoyed reading it.')
            ->andReturn($mockResults);
        
        $job = new ProcessComprehendJob($processingJob->id);
        $job->handle($this->comprehendService, app('Psr\\Log\\LoggerInterface'));
        
        // Check processing job was updated
        $processingJob->refresh();
        expect($processingJob->status)->toBe(JobStatus::COMPLETED);
        expect($processingJob->started_at)->not->toBeNull();
        expect($processingJob->completed_at)->not->toBeNull();
        
        // Check analysis result was created
        $result = DocumentAnalysisResult::where([
            'document_id' => $this->document->id,
            'analysis_type' => 'comprehend_sentiment'
        ])->first();
        
        expect($result)->not->toBeNull();
        expect($result->raw_results)->toEqual($mockResults);
        expect($result->confidence_score)->toEqual(0.85); // Max confidence score
        
        $processedData = $result->processed_data;
        expect($processedData['sentiment'])->toBe('POSITIVE');
        expect($processedData['confidence_scores']['Positive'])->toBe(0.85);
    });
    
    test('processes entity detection successfully', function () {
        DocumentAnalysisResult::factory()->create([
            'document_id' => $this->document->id,
            'analysis_type' => 'textract_text',
            'processed_data' => [
                'text_blocks' => [
                    ['text' => 'John Doe works at Amazon in Seattle.'],
                ]
            ],
        ]);
        
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'comprehend_entities',
            'status' => JobStatus::PENDING,
        ]);
        
        $mockResults = [
            'Entities' => [
                [
                    'Text' => 'John Doe',
                    'Type' => 'PERSON',
                    'Score' => 0.95,
                ],
                [
                    'Text' => 'Amazon',
                    'Type' => 'ORGANIZATION',
                    'Score' => 0.88,
                ],
                [
                    'Text' => 'Seattle',
                    'Type' => 'LOCATION',
                    'Score' => 0.92,
                ]
            ],
            'ResponseMetadata' => ['RequestId' => 'entities-456']
        ];
        
        $this->comprehendService->shouldReceive('detectEntities')
            ->once()
            ->with('John Doe works at Amazon in Seattle.')
            ->andReturn($mockResults);
        
        $job = new ProcessComprehendJob($processingJob->id);
        $job->handle($this->comprehendService, app('Psr\\Log\\LoggerInterface'));
        
        $result = DocumentAnalysisResult::where([
            'document_id' => $this->document->id,
            'analysis_type' => 'comprehend_entities'
        ])->first();
        
        expect((float) $result->confidence_score)->toBeGreaterThan(0.91);
        expect((float) $result->confidence_score)->toBeLessThan(0.92); // Average of 0.95, 0.88, 0.92
        
        $processedData = $result->processed_data;
        expect($processedData['entities'])->toHaveCount(3);
        expect($processedData['entities'][0]['text'])->toBe('John Doe');
        expect($processedData['entities'][0]['type'])->toBe('PERSON');
        expect($processedData['entities'][0]['confidence'])->toBe(0.95);
    });
    
    test('processes key phrases detection successfully', function () {
        DocumentAnalysisResult::factory()->create([
            'document_id' => $this->document->id,
            'analysis_type' => 'textract_text',
            'processed_data' => [
                'text_blocks' => [
                    ['text' => 'The quarterly financial report shows strong growth.'],
                ]
            ],
        ]);
        
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'comprehend_key_phrases',
            'status' => JobStatus::PENDING,
        ]);
        
        $mockResults = [
            'KeyPhrases' => [
                [
                    'Text' => 'quarterly financial report',
                    'Score' => 0.91,
                ],
                [
                    'Text' => 'strong growth',
                    'Score' => 0.87,
                ]
            ],
            'ResponseMetadata' => ['RequestId' => 'phrases-789']
        ];
        
        $this->comprehendService->shouldReceive('detectKeyPhrases')
            ->once()
            ->andReturn($mockResults);
        
        $job = new ProcessComprehendJob($processingJob->id);
        $job->handle($this->comprehendService, app('Psr\\Log\\LoggerInterface'));
        
        $result = DocumentAnalysisResult::where([
            'document_id' => $this->document->id,
            'analysis_type' => 'comprehend_key_phrases'
        ])->first();
        
        expect($result->confidence_score)->toEqual(0.89); // Average of 0.91, 0.87
        
        $processedData = $result->processed_data;
        expect($processedData['key_phrases'])->toHaveCount(2);
        expect($processedData['key_phrases'][0]['text'])->toBe('quarterly financial report');
        expect($processedData['key_phrases'][0]['confidence'])->toBe(0.91);
    });
    
    test('processes language detection successfully', function () {
        DocumentAnalysisResult::factory()->create([
            'document_id' => $this->document->id,
            'analysis_type' => 'textract_text',
            'processed_data' => [
                'text_blocks' => [
                    ['text' => 'This is an English document.'],
                ]
            ],
        ]);
        
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'comprehend_language',
            'status' => JobStatus::PENDING,
        ]);
        
        $mockResults = [
            'Languages' => [
                [
                    'LanguageCode' => 'en',
                    'Score' => 0.99,
                ]
            ],
            'ResponseMetadata' => ['RequestId' => 'language-101']
        ];
        
        $this->comprehendService->shouldReceive('detectLanguage')
            ->once()
            ->andReturn($mockResults);
        
        $job = new ProcessComprehendJob($processingJob->id);
        $job->handle($this->comprehendService, app('Psr\\Log\\LoggerInterface'));
        
        $result = DocumentAnalysisResult::where([
            'document_id' => $this->document->id,
            'analysis_type' => 'comprehend_language'
        ])->first();
        
        expect($result->confidence_score)->toEqual(0.99);
        
        $processedData = $result->processed_data;
        expect($processedData['languages'])->toHaveCount(1);
        expect($processedData['languages'][0]['code'])->toBe('en');
        expect($processedData['languages'][0]['confidence'])->toBe(0.99);
    });
    
    test('handles non-existent processing job gracefully', function () {
        $job = new ProcessComprehendJob(999);
        $job->handle($this->comprehendService, app('Psr\\Log\\LoggerInterface'));
        
        // Should not create any analysis results
        expect(DocumentAnalysisResult::count())->toBe(0);
    });
    
    test('handles documents without text content', function () {
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'comprehend_sentiment',
            'status' => JobStatus::PENDING,
        ]);
        
        // No Textract results exist, so no text available
        
        expect(function () use ($processingJob) {
            $job = new ProcessComprehendJob($processingJob->id);
            $job->handle($this->comprehendService, app('Psr\\Log\\LoggerInterface'));
        })->toThrow(\RuntimeException::class, 'No text available for Comprehend analysis');
        
        $processingJob->refresh();
        expect($processingJob->status)->toBe(JobStatus::FAILED);
    });
    
    test('handles Comprehend service errors', function () {
        DocumentAnalysisResult::factory()->create([
            'document_id' => $this->document->id,
            'analysis_type' => 'textract_text',
            'processed_data' => [
                'text_blocks' => [['text' => 'Test text']],
            ],
        ]);
        
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'comprehend_sentiment',
            'status' => JobStatus::PENDING,
        ]);
        
        $this->comprehendService->shouldReceive('detectSentiment')
            ->once()
            ->andThrow(new \Exception('Comprehend API error'));
        
        expect(function () use ($processingJob) {
            $job = new ProcessComprehendJob($processingJob->id);
            $job->handle($this->comprehendService, app('Psr\\Log\\LoggerInterface'));
        })->toThrow(\Exception::class);
        
        $processingJob->refresh();
        expect($processingJob->status)->toBe(JobStatus::FAILED);
        expect($processingJob->error_message)->toContain('Comprehend API error');
    });
    
    test('throws exception for unknown job type', function () {
        DocumentAnalysisResult::factory()->create([
            'document_id' => $this->document->id,
            'analysis_type' => 'textract_text',
            'processed_data' => [
                'text_blocks' => [['text' => 'Test text']],
            ],
        ]);
        
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'unknown_comprehend_type',
            'status' => JobStatus::PENDING,
        ]);
        
        expect(function () use ($processingJob) {
            $job = new ProcessComprehendJob($processingJob->id);
            $job->handle($this->comprehendService, app('Psr\\Log\\LoggerInterface'));
        })->toThrow(\InvalidArgumentException::class);
    });
    
    test('marks document as completed when all jobs finished', function () {
        DocumentAnalysisResult::factory()->create([
            'document_id' => $this->document->id,
            'analysis_type' => 'textract_text',
            'processed_data' => [
                'text_blocks' => [['text' => 'Test text']],
            ],
        ]);
        
        // Create another processing job that's already completed
        DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'textract_text',
            'status' => JobStatus::COMPLETED,
        ]);
        
        // Create the current job (last pending one)
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'comprehend_sentiment',
            'status' => JobStatus::PENDING,
        ]);
        
        $this->comprehendService->shouldReceive('detectSentiment')
            ->once()
            ->andReturn([
                'Sentiment' => 'NEUTRAL',
                'SentimentScore' => ['Neutral' => 0.8],
                'ResponseMetadata' => []
            ]);
        
        $job = new ProcessComprehendJob($processingJob->id);
        $job->handle($this->comprehendService, app('Psr\\Log\\LoggerInterface'));
        
        // Document should be marked as completed since all jobs are done
        $this->document->refresh();
        expect($this->document->processing_status)->toBe(ProcessingStatus::COMPLETED);
    });
    
    test('does not mark document as completed when jobs still pending', function () {
        DocumentAnalysisResult::factory()->create([
            'document_id' => $this->document->id,
            'analysis_type' => 'textract_text',
            'processed_data' => [
                'text_blocks' => [['text' => 'Test text']],
            ],
        ]);
        
        // Create another processing job that's still pending
        DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'comprehend_entities',
            'status' => JobStatus::PENDING,
        ]);
        
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'comprehend_sentiment',
            'status' => JobStatus::PENDING,
        ]);
        
        $this->comprehendService->shouldReceive('detectSentiment')
            ->once()
            ->andReturn([
                'Sentiment' => 'NEUTRAL',
                'SentimentScore' => ['Neutral' => 0.8],
                'ResponseMetadata' => []
            ]);
        
        $job = new ProcessComprehendJob($processingJob->id);
        $job->handle($this->comprehendService, app('Psr\\Log\\LoggerInterface'));
        
        // Document should still be processing since other jobs are pending
        $this->document->refresh();
        expect($this->document->processing_status)->toBe(ProcessingStatus::PROCESSING);
    });
    
    test('extracts text from multiple Textract text blocks', function () {
        DocumentAnalysisResult::factory()->create([
            'document_id' => $this->document->id,
            'analysis_type' => 'textract_text',
            'processed_data' => [
                'text_blocks' => [
                    ['text' => 'First paragraph text.'],
                    ['text' => 'Second paragraph text.'],
                    ['text' => 'Third paragraph text.'],
                ]
            ],
        ]);
        
        $processingJob = DocumentProcessingJob::factory()->create([
            'document_id' => $this->document->id,
            'job_type' => 'comprehend_sentiment',
            'status' => JobStatus::PENDING,
        ]);
        
        $this->comprehendService->shouldReceive('detectSentiment')
            ->once()
            ->with('First paragraph text. Second paragraph text. Third paragraph text.')
            ->andReturn([
                'Sentiment' => 'NEUTRAL',
                'SentimentScore' => ['Neutral' => 0.7],
                'ResponseMetadata' => []
            ]);
        
        $job = new ProcessComprehendJob($processingJob->id);
        $job->handle($this->comprehendService, app('Psr\\Log\\LoggerInterface'));
        
        // Should have successfully processed the combined text
        $result = DocumentAnalysisResult::where([
            'document_id' => $this->document->id,
            'analysis_type' => 'comprehend_sentiment'
        ])->first();
        
        expect($result)->not->toBeNull();
        expect($result->metadata['text_length'])->toBe(strlen('First paragraph text. Second paragraph text. Third paragraph text.'));
    });
});