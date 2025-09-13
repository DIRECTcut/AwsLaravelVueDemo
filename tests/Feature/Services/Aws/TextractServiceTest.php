<?php

use App\Exceptions\Aws\DocumentAnalysisException;
use App\Services\Aws\TextractService;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\Textract\TextractClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->textractClient = Mockery::mock(TextractClient::class);
    $this->textractService = new TextractService($this->textractClient);
});

afterEach(function () {
    Mockery::close();
});

describe('Synchronous Operations', function () {
    test('detects document text successfully', function () {
        $s3Key = 'documents/test.pdf';
        $s3Bucket = 'test-bucket';
        $mockResult = new Result([
            'Blocks' => [
                [
                    'BlockType' => 'LINE',
                    'Text' => 'Sample text',
                    'Confidence' => 99.5,
                ],
            ],
            'ResponseMetadata' => ['RequestId' => 'test-123'],
        ]);

        $this->textractClient->shouldReceive('detectDocumentText')
            ->once()
            ->with([
                'Document' => [
                    'S3Object' => [
                        'Bucket' => $s3Bucket,
                        'Name' => $s3Key,
                    ],
                ],
            ])
            ->andReturn($mockResult);

        $result = $this->textractService->detectDocumentText($s3Key, $s3Bucket);

        expect($result)->toBeArray();
        expect($result['Blocks'])->toHaveCount(1);
        expect($result['Blocks'][0]['Text'])->toBe('Sample text');
    });

    test('handles rate limit exception in detectDocumentText', function () {
        $s3Key = 'documents/test.pdf';
        $s3Bucket = 'test-bucket';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('ProvisionedThroughputExceededException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Rate limit exceeded');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Rate limit exceeded');
        $awsException->shouldReceive('getCode')
            ->andReturn(429);

        $this->textractClient->shouldReceive('detectDocumentText')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($s3Key, $s3Bucket) {
            $this->textractService->detectDocumentText($s3Key, $s3Bucket);
        })->toThrow(DocumentAnalysisException::class, 'Textract rate limit exceeded. Please try again later.');
    });

    test('handles invalid document exception', function () {
        $s3Key = 'documents/corrupted.pdf';
        $s3Bucket = 'test-bucket';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('InvalidS3ObjectException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Invalid document');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Invalid document');
        $awsException->shouldReceive('getCode')
            ->andReturn(400);

        $this->textractClient->shouldReceive('detectDocumentText')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($s3Key, $s3Bucket) {
            $this->textractService->detectDocumentText($s3Key, $s3Bucket);
        })->toThrow(DocumentAnalysisException::class, 'Invalid document format or corrupted file.');
    });

    test('handles document too large exception', function () {
        $s3Key = 'documents/large.pdf';
        $s3Bucket = 'test-bucket';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('DocumentTooLargeException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Document too large');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Document too large');
        $awsException->shouldReceive('getCode')
            ->andReturn(413);

        $this->textractClient->shouldReceive('detectDocumentText')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($s3Key, $s3Bucket) {
            $this->textractService->detectDocumentText($s3Key, $s3Bucket);
        })->toThrow(DocumentAnalysisException::class, 'Document is too large for synchronous processing. Maximum size is 5MB.');
    });

    test('analyzes document for forms and tables', function () {
        $s3Key = 'documents/form.pdf';
        $s3Bucket = 'test-bucket';
        $featureTypes = ['FORMS', 'TABLES'];
        
        $mockResult = new Result([
            'Blocks' => [
                [
                    'BlockType' => 'TABLE',
                    'Id' => 'table-1',
                    'Confidence' => 95.2,
                ],
                [
                    'BlockType' => 'KEY_VALUE_SET',
                    'EntityTypes' => ['KEY'],
                    'Text' => 'Name:',
                    'Confidence' => 98.1,
                ],
            ],
            'ResponseMetadata' => ['RequestId' => 'test-456'],
        ]);

        $this->textractClient->shouldReceive('analyzeDocument')
            ->once()
            ->with([
                'Document' => [
                    'S3Object' => [
                        'Bucket' => $s3Bucket,
                        'Name' => $s3Key,
                    ],
                ],
                'FeatureTypes' => $featureTypes,
            ])
            ->andReturn($mockResult);

        $result = $this->textractService->analyzeDocument($s3Key, $s3Bucket, $featureTypes);

        expect($result)->toBeArray();
        expect($result['Blocks'])->toHaveCount(2);
        expect($result['Blocks'][0]['BlockType'])->toBe('TABLE');
    });

    test('handles unsupported document exception in analyzeDocument', function () {
        $s3Key = 'documents/unsupported.xyz';
        $s3Bucket = 'test-bucket';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('UnsupportedDocumentException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Unsupported document');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Unsupported document');
        $awsException->shouldReceive('getCode')
            ->andReturn(415);

        $this->textractClient->shouldReceive('analyzeDocument')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($s3Key, $s3Bucket) {
            $this->textractService->analyzeDocument($s3Key, $s3Bucket);
        })->toThrow(DocumentAnalysisException::class, 'Document format not supported for analysis.');
    });
});

describe('Asynchronous Operations', function () {
    test('starts document text detection job', function () {
        $s3Key = 'documents/large.pdf';
        $s3Bucket = 'test-bucket';
        $jobId = 'job-abc-123';

        config([
            'services.aws.textract.notification_role_arn' => 'arn:aws:iam::123456789012:role/TextractRole',
            'services.aws.textract.sns_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:TextractTopic',
        ]);

        $this->textractClient->shouldReceive('startDocumentTextDetection')
            ->once()
            ->with([
                'DocumentLocation' => [
                    'S3Object' => [
                        'Bucket' => $s3Bucket,
                        'Name' => $s3Key,
                    ],
                ],
                'NotificationChannel' => [
                    'RoleArn' => 'arn:aws:iam::123456789012:role/TextractRole',
                    'SNSTopicArn' => 'arn:aws:sns:us-east-1:123456789012:TextractTopic',
                ],
            ])
            ->andReturn(['JobId' => $jobId]);

        $result = $this->textractService->startDocumentTextDetection($s3Key, $s3Bucket);

        expect($result)->toBe($jobId);
    });

    test('gets document text detection results when succeeded', function () {
        $jobId = 'job-abc-123';
        
        $this->textractClient->shouldReceive('getDocumentTextDetection')
            ->once()
            ->with(['JobId' => $jobId])
            ->andReturn([
                'JobStatus' => 'SUCCEEDED',
                'Blocks' => [
                    ['BlockType' => 'LINE', 'Text' => 'Page 1 text'],
                ],
                'NextToken' => null,
            ]);

        $result = $this->textractService->getDocumentTextDetectionResults($jobId);

        expect($result)->toBeArray();
        expect($result['JobStatus'])->toBe('SUCCEEDED');
        expect($result['Blocks'])->toHaveCount(1);
    });

    test('returns null when job is in progress', function () {
        $jobId = 'job-abc-123';
        
        $this->textractClient->shouldReceive('getDocumentTextDetection')
            ->once()
            ->with(['JobId' => $jobId])
            ->andReturn([
                'JobStatus' => 'IN_PROGRESS',
            ]);

        $result = $this->textractService->getDocumentTextDetectionResults($jobId);

        expect($result)->toBeNull();
    });

    test('throws exception when job failed', function () {
        $jobId = 'job-abc-123';
        
        $this->textractClient->shouldReceive('getDocumentTextDetection')
            ->once()
            ->with(['JobId' => $jobId])
            ->andReturn([
                'JobStatus' => 'FAILED',
                'StatusMessage' => 'Processing failed due to corrupted document',
            ]);

        expect(function () use ($jobId) {
            $this->textractService->getDocumentTextDetectionResults($jobId);
        })->toThrow(DocumentAnalysisException::class, 'Text detection job failed: Processing failed due to corrupted document');
    });

    test('aggregates results from multiple pages', function () {
        $jobId = 'job-abc-123';
        
        // First call returns first page with NextToken
        $this->textractClient->shouldReceive('getDocumentTextDetection')
            ->once()
            ->with(['JobId' => $jobId])
            ->andReturn([
                'JobStatus' => 'SUCCEEDED',
                'Blocks' => [
                    ['BlockType' => 'LINE', 'Text' => 'Page 1 text'],
                ],
                'NextToken' => 'token-123',
            ]);

        // Second call returns second page without NextToken
        $this->textractClient->shouldReceive('getDocumentTextDetection')
            ->once()
            ->with(['JobId' => $jobId, 'NextToken' => 'token-123'])
            ->andReturn([
                'Blocks' => [
                    ['BlockType' => 'LINE', 'Text' => 'Page 2 text'],
                ],
                'NextToken' => null,
            ]);

        $result = $this->textractService->getDocumentTextDetectionResults($jobId);

        expect($result)->toBeArray();
        expect($result['Blocks'])->toHaveCount(2);
        expect($result['Blocks'][0]['Text'])->toBe('Page 1 text');
        expect($result['Blocks'][1]['Text'])->toBe('Page 2 text');
        expect($result)->not->toHaveKey('NextToken');
    });

    test('starts document analysis job', function () {
        $s3Key = 'documents/form.pdf';
        $s3Bucket = 'test-bucket';
        $jobId = 'job-xyz-456';
        $featureTypes = ['FORMS', 'TABLES'];

        config([
            'services.aws.textract.notification_role_arn' => 'arn:aws:iam::123456789012:role/TextractRole',
            'services.aws.textract.sns_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:TextractTopic',
        ]);

        $this->textractClient->shouldReceive('startDocumentAnalysis')
            ->once()
            ->with([
                'DocumentLocation' => [
                    'S3Object' => [
                        'Bucket' => $s3Bucket,
                        'Name' => $s3Key,
                    ],
                ],
                'FeatureTypes' => $featureTypes,
                'NotificationChannel' => [
                    'RoleArn' => 'arn:aws:iam::123456789012:role/TextractRole',
                    'SNSTopicArn' => 'arn:aws:sns:us-east-1:123456789012:TextractTopic',
                ],
            ])
            ->andReturn(['JobId' => $jobId]);

        $result = $this->textractService->startDocumentAnalysis($s3Key, $s3Bucket, $featureTypes);

        expect($result)->toBe($jobId);
    });

    test('gets document analysis results when succeeded', function () {
        $jobId = 'job-xyz-456';
        
        $this->textractClient->shouldReceive('getDocumentAnalysis')
            ->once()
            ->with(['JobId' => $jobId])
            ->andReturn([
                'JobStatus' => 'SUCCEEDED',
                'Blocks' => [
                    ['BlockType' => 'TABLE', 'Id' => 'table-1'],
                ],
                'NextToken' => null,
            ]);

        $result = $this->textractService->getDocumentAnalysisResults($jobId);

        expect($result)->toBeArray();
        expect($result['JobStatus'])->toBe('SUCCEEDED');
        expect($result['Blocks'][0]['BlockType'])->toBe('TABLE');
    });

    test('handles AWS exception in async operations', function () {
        $s3Key = 'documents/test.pdf';
        $s3Bucket = 'test-bucket';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('AccessDeniedException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Access denied');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Access denied');
        $awsException->shouldReceive('getCode')
            ->andReturn(403);

        $this->textractClient->shouldReceive('startDocumentTextDetection')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($s3Key, $s3Bucket) {
            $this->textractService->startDocumentTextDetection($s3Key, $s3Bucket);
        })->toThrow(DocumentAnalysisException::class, 'Failed to start document text detection: Access denied');
    });
});