<?php

use App\Contracts\Aws\DocumentAnalysisServiceInterface;
use App\Services\Aws\FakeTextractService;
use Mockery;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    $this->logger = Mockery::mock(LoggerInterface::class);
    $this->service = new FakeTextractService($this->logger);
});

afterEach(function () {
    Mockery::close();
});

describe('FakeTextractService Interface Compliance', function () {
    test('implements DocumentAnalysisServiceInterface', function () {
        // Arrange - service created in beforeEach

        // Act & Assert
        expect($this->service)->toBeInstanceOf(DocumentAnalysisServiceInterface::class);
    });
});

describe('Document Text Detection', function () {
    test('detectDocumentText returns valid structure', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $s3Key = 'test-key';
        $s3Bucket = 'test-bucket';

        // Act
        $result = $this->service->detectDocumentText($s3Key, $s3Bucket);

        // Assert
        expect($result)->toBeArray()
            ->toHaveKeys(['text', 'Blocks', 'confidence', 'page_count']);

        expect($result['text'])->toBeString();
        expect($result['Blocks'])->toBeArray();
        expect($result['confidence'])->toBeFloat()->toBeBetween(0, 100);
        expect($result['page_count'])->toBeInt()->toBeGreaterThan(0);
    });

    test('detectDocumentText blocks have correct structure', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $s3Key = 'test-key';
        $s3Bucket = 'test-bucket';

        // Act
        $result = $this->service->detectDocumentText($s3Key, $s3Bucket);

        // Assert
        expect($result['Blocks'])->not->toBeEmpty();

        foreach ($result['Blocks'] as $block) {
            expect($block)->toBeArray()
                ->toHaveKeys(['Text', 'Confidence', 'BlockType']);

            expect($block['Text'])->toBeString();
            expect($block['Confidence'])->toBeFloat()->toBeBetween(0, 100);
            expect($block['BlockType'])->toBe('LINE');
        }
    });
});

describe('Document Analysis', function () {
    test('analyzeDocument returns valid structure with default features', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $s3Key = 'test-key';
        $s3Bucket = 'test-bucket';

        // Act
        $result = $this->service->analyzeDocument($s3Key, $s3Bucket);

        // Assert
        expect($result)->toBeArray()
            ->toHaveKeys(['text', 'Blocks', 'confidence', 'page_count', 'tables', 'forms']);
    });

    test('analyzeDocument returns tables when TABLES feature requested', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $s3Key = 'test-key';
        $s3Bucket = 'test-bucket';
        $features = ['TABLES'];

        // Act
        $result = $this->service->analyzeDocument($s3Key, $s3Bucket, $features);

        // Assert
        expect($result)->toHaveKey('tables');
        expect($result['tables'])->toBeArray()->not->toBeEmpty();

        foreach ($result['tables'] as $table) {
            expect($table)->toBeArray()
                ->toHaveKeys(['rows', 'columns', 'cells']);

            expect($table['rows'])->toBeInt()->toBeGreaterThan(0);
            expect($table['columns'])->toBeInt()->toBeGreaterThan(0);
            expect($table['cells'])->toBeArray();
        }
    });

    test('analyzeDocument returns forms when FORMS feature requested', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $s3Key = 'test-key';
        $s3Bucket = 'test-bucket';
        $features = ['FORMS'];

        // Act
        $result = $this->service->analyzeDocument($s3Key, $s3Bucket, $features);

        // Assert
        expect($result)->toHaveKey('forms');
        expect($result['forms'])->toBeArray()->not->toBeEmpty();

        foreach ($result['forms'] as $form) {
            expect($form)->toBeArray()
                ->toHaveKeys(['key', 'value']);

            expect($form['key'])->toBeString();
            expect($form['value'])->toBeString();
        }
    });

    test('analyzeDocument excludes features not requested', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $s3Key = 'test-key';
        $s3Bucket = 'test-bucket';
        $features = ['TABLES'];

        // Act
        $result = $this->service->analyzeDocument($s3Key, $s3Bucket, $features);

        // Assert
        expect($result)->toHaveKey('tables');
        expect($result)->not->toHaveKey('forms');
    });
});

describe('Asynchronous Operations', function () {
    test('startDocumentTextDetection returns job ID', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $s3Key = 'test-key';
        $s3Bucket = 'test-bucket';

        // Act
        $jobId = $this->service->startDocumentTextDetection($s3Key, $s3Bucket);

        // Assert
        expect($jobId)->toBeString()
            ->toStartWith('fake-job-');
    });

    test('getDocumentTextDetectionResults returns valid structure', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $jobId = 'fake-job-123';

        // Act
        $result = $this->service->getDocumentTextDetectionResults($jobId);

        // Assert
        expect($result)->toBeArray()
            ->toHaveKeys(['status', 'text', 'Blocks', 'confidence', 'page_count']);

        expect($result['status'])->toBe('SUCCEEDED');
    });

    test('startDocumentAnalysis returns job ID', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $s3Key = 'test-key';
        $s3Bucket = 'test-bucket';

        // Act
        $jobId = $this->service->startDocumentAnalysis($s3Key, $s3Bucket);

        // Assert
        expect($jobId)->toBeString()
            ->toStartWith('fake-analysis-');
    });

    test('getDocumentAnalysisResults returns valid structure', function () {
        // Arrange
        $this->logger->shouldReceive('info')->twice(); // One for getResults, one for analyzeDocument
        $jobId = 'fake-job-123';

        // Act
        $result = $this->service->getDocumentAnalysisResults($jobId);

        // Assert
        expect($result)->toBeArray()
            ->toHaveKeys(['text', 'Blocks', 'confidence', 'page_count']);
    });
});

describe('Data Structure Consistency for ProcessTextractJob', function () {
    test('returned data structure matches what ProcessTextractJob expects', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $s3Key = 'test-key';
        $s3Bucket = 'test-bucket';

        // Act
        $result = $this->service->analyzeDocument($s3Key, $s3Bucket);

        // Assert - Verify the critical 'Blocks' key exists (not 'blocks')
        expect($result)->toHaveKey('Blocks');
        expect($result)->not->toHaveKey('blocks');

        // Assert - Verify blocks structure matches what ProcessTextractJob processes
        expect($result['Blocks'])->toBeArray();

        foreach ($result['Blocks'] as $block) {
            expect($block)->toHaveKeys(['Text', 'Confidence', 'BlockType']);
            expect($block['BlockType'])->toBe('LINE');
        }
    });

    test('blocks structure allows ProcessTextractJob to extract text_blocks', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $s3Key = 'test-key';
        $s3Bucket = 'test-bucket';

        // Act
        $result = $this->service->analyzeDocument($s3Key, $s3Bucket);

        // Act & Assert - Simulate what ProcessTextractJob does
        $textBlocks = [];
        foreach ($result['Blocks'] ?? [] as $block) {
            if ($block['BlockType'] === 'LINE') {
                $textBlocks[] = [
                    'text' => $block['Text'] ?? '',
                    'confidence' => $block['Confidence'] ?? 0,
                    'geometry' => $block['Geometry'] ?? null,
                ];
            }
        }

        expect($textBlocks)->not->toBeEmpty();
        expect($textBlocks[0])->toHaveKey('text');
        expect($textBlocks[0]['text'])->not->toBeEmpty();
    });
});
