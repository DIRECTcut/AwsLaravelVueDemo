<?php

use App\Exceptions\Aws\TextAnalysisException;
use App\Services\Aws\ComprehendService;
use Aws\Comprehend\ComprehendClient;
use Aws\Exception\AwsException;
use Aws\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->comprehendClient = Mockery::mock(ComprehendClient::class);
    $this->comprehendService = new ComprehendService($this->comprehendClient);
});

afterEach(function () {
    Mockery::close();
});

describe('Sentiment Detection', function () {
    test('detects sentiment successfully', function () {
        $text = 'I absolutely love this product! It works great.';

        $mockResult = new Result([
            'Sentiment' => 'POSITIVE',
            'SentimentScore' => [
                'Positive' => 0.95,
                'Negative' => 0.02,
                'Neutral' => 0.02,
                'Mixed' => 0.01,
            ],
            'ResponseMetadata' => ['RequestId' => 'sentiment-test-123'],
        ]);

        $this->comprehendClient->shouldReceive('detectSentiment')
            ->once()
            ->with([
                'Text' => $text,
                'LanguageCode' => 'en',
            ])
            ->andReturn($mockResult);

        $result = $this->comprehendService->detectSentiment($text);

        expect($result)->toBeArray();
        expect($result['Sentiment'])->toBe('POSITIVE');
        expect($result['SentimentScore']['Positive'])->toBe(0.95);
    });

    test('handles text size limit exception', function () {
        $longText = str_repeat('This is a very long text. ', 1000);

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('TextSizeLimitExceededException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Text size exceeded');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Text size exceeded');
        $awsException->shouldReceive('getCode')
            ->andReturn(413);

        $this->comprehendClient->shouldReceive('detectSentiment')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($longText) {
            $this->comprehendService->detectSentiment($longText);
        })->toThrow(TextAnalysisException::class, 'Text is too large for sentiment analysis. Maximum size is 5000 UTF-8 bytes.');
    });

    test('handles unsupported language exception', function () {
        $text = 'Test text';
        $unsupportedLang = 'xyz';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('UnsupportedLanguageException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Language not supported');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Language not supported');
        $awsException->shouldReceive('getCode')
            ->andReturn(400);

        $this->comprehendClient->shouldReceive('detectSentiment')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($text, $unsupportedLang) {
            $this->comprehendService->detectSentiment($text, $unsupportedLang);
        })->toThrow(TextAnalysisException::class, "Language 'xyz' is not supported for sentiment analysis.");
    });

    test('handles generic AWS exception for sentiment detection', function () {
        $text = 'Test text';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('InternalServerError');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Internal server error occurred');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Internal server error');
        $awsException->shouldReceive('getCode')
            ->andReturn(500);

        $this->comprehendClient->shouldReceive('detectSentiment')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($text) {
            $this->comprehendService->detectSentiment($text);
        })->toThrow(TextAnalysisException::class, 'Failed to detect sentiment: Internal server error occurred');
    });
});

describe('Entity Detection', function () {
    test('detects entities successfully', function () {
        $text = 'Amazon was founded by Jeff Bezos in Seattle.';

        $mockResult = new Result([
            'Entities' => [
                [
                    'Text' => 'Amazon',
                    'Type' => 'ORGANIZATION',
                    'Score' => 0.99,
                    'BeginOffset' => 0,
                    'EndOffset' => 6,
                ],
                [
                    'Text' => 'Jeff Bezos',
                    'Type' => 'PERSON',
                    'Score' => 0.98,
                    'BeginOffset' => 21,
                    'EndOffset' => 31,
                ],
                [
                    'Text' => 'Seattle',
                    'Type' => 'LOCATION',
                    'Score' => 0.95,
                    'BeginOffset' => 35,
                    'EndOffset' => 42,
                ],
            ],
            'ResponseMetadata' => ['RequestId' => 'entities-test-456'],
        ]);

        $this->comprehendClient->shouldReceive('detectEntities')
            ->once()
            ->with([
                'Text' => $text,
                'LanguageCode' => 'en',
            ])
            ->andReturn($mockResult);

        $result = $this->comprehendService->detectEntities($text);

        expect($result)->toBeArray();
        expect($result['Entities'])->toHaveCount(3);
        expect($result['Entities'][0]['Type'])->toBe('ORGANIZATION');
        expect($result['Entities'][1]['Type'])->toBe('PERSON');
        expect($result['Entities'][2]['Type'])->toBe('LOCATION');
    });

    test('handles entity detection text size limit', function () {
        $longText = str_repeat('Entity text ', 10000);

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('TextSizeLimitExceededException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Text too large');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Text too large');
        $awsException->shouldReceive('getCode')
            ->andReturn(413);

        $this->comprehendClient->shouldReceive('detectEntities')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($longText) {
            $this->comprehendService->detectEntities($longText);
        })->toThrow(TextAnalysisException::class, 'Text is too large for entity detection. Maximum size is 100 KB for UTF-8 encoded characters.');
    });

    test('handles generic AWS exception for entity detection', function () {
        $text = 'Test text';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('ThrottlingException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Rate exceeded');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Rate exceeded');
        $awsException->shouldReceive('getCode')
            ->andReturn(429);

        $this->comprehendClient->shouldReceive('detectEntities')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($text) {
            $this->comprehendService->detectEntities($text);
        })->toThrow(TextAnalysisException::class, 'Failed to detect entities: Rate exceeded');
    });
});

describe('Key Phrase Detection', function () {
    test('detects key phrases successfully', function () {
        $text = 'The quarterly financial report shows strong revenue growth.';

        $mockResult = new Result([
            'KeyPhrases' => [
                [
                    'Text' => 'The quarterly financial report',
                    'Score' => 0.92,
                    'BeginOffset' => 0,
                    'EndOffset' => 30,
                ],
                [
                    'Text' => 'strong revenue growth',
                    'Score' => 0.88,
                    'BeginOffset' => 37,
                    'EndOffset' => 58,
                ],
            ],
            'ResponseMetadata' => ['RequestId' => 'phrases-test-789'],
        ]);

        $this->comprehendClient->shouldReceive('detectKeyPhrases')
            ->once()
            ->with([
                'Text' => $text,
                'LanguageCode' => 'en',
            ])
            ->andReturn($mockResult);

        $result = $this->comprehendService->detectKeyPhrases($text);

        expect($result)->toBeArray();
        expect($result['KeyPhrases'])->toHaveCount(2);
        expect($result['KeyPhrases'][0]['Text'])->toBe('The quarterly financial report');
        expect($result['KeyPhrases'][0]['Score'])->toBe(0.92);
    });

    test('handles generic AWS exception for key phrase detection', function () {
        $text = 'Test text';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('AccessDeniedException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Access denied');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Access denied');
        $awsException->shouldReceive('getCode')
            ->andReturn(403);

        $this->comprehendClient->shouldReceive('detectKeyPhrases')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($text) {
            $this->comprehendService->detectKeyPhrases($text);
        })->toThrow(TextAnalysisException::class, 'Failed to detect key phrases: Access denied');
    });
});

describe('Language Detection', function () {
    test('detects language successfully', function () {
        $text = 'This is an English text sample.';

        $mockResult = new Result([
            'Languages' => [
                [
                    'LanguageCode' => 'en',
                    'Score' => 0.99,
                ],
                [
                    'LanguageCode' => 'es',
                    'Score' => 0.01,
                ],
            ],
            'ResponseMetadata' => ['RequestId' => 'language-test-101'],
        ]);

        $this->comprehendClient->shouldReceive('detectDominantLanguage')
            ->once()
            ->with([
                'Text' => $text,
            ])
            ->andReturn($mockResult);

        $result = $this->comprehendService->detectLanguage($text);

        expect($result)->toBeArray();
        expect($result['Languages'])->toHaveCount(2);
        expect($result['Languages'][0]['LanguageCode'])->toBe('en');
        expect($result['Languages'][0]['Score'])->toBe(0.99);
    });

    test('handles generic AWS exception for language detection', function () {
        $text = 'Test text';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('ServiceUnavailable');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Service temporarily unavailable');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Service unavailable');
        $awsException->shouldReceive('getCode')
            ->andReturn(503);

        $this->comprehendClient->shouldReceive('detectDominantLanguage')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($text) {
            $this->comprehendService->detectLanguage($text);
        })->toThrow(TextAnalysisException::class, 'Failed to detect language: Service temporarily unavailable');
    });
});

describe('Batch Jobs', function () {
    test('starts entities detection job successfully', function () {
        $inputConfig = ['S3Uri' => 's3://bucket/input/', 'InputFormat' => 'ONE_DOC_PER_LINE'];
        $outputConfig = ['S3Uri' => 's3://bucket/output/'];
        $roleArn = 'arn:aws:iam::123456789012:role/ComprehendRole';

        $this->comprehendClient->shouldReceive('startEntitiesDetectionJob')
            ->once()
            ->with(Mockery::on(function ($args) use ($inputConfig, $outputConfig, $roleArn) {
                return $args['InputDataConfig'] === $inputConfig &&
                       $args['OutputDataConfig'] === $outputConfig &&
                       $args['DataAccessRoleArn'] === $roleArn &&
                       $args['LanguageCode'] === 'en' &&
                       str_starts_with($args['JobName'], 'entities-detection-');
            }))
            ->andReturn([
                'JobId' => 'job-entities-123',
                'JobArn' => 'arn:aws:comprehend:us-east-1:123456789012:entities-detection-job/job-entities-123',
                'JobStatus' => 'SUBMITTED',
            ]);

        $jobId = $this->comprehendService->startEntitiesDetectionJob(
            $inputConfig,
            $outputConfig,
            $roleArn
        );

        expect($jobId)->toBe('job-entities-123');
    });

    test('starts sentiment detection job successfully', function () {
        $inputConfig = ['S3Uri' => 's3://bucket/input/', 'InputFormat' => 'ONE_DOC_PER_FILE'];
        $outputConfig = ['S3Uri' => 's3://bucket/output/'];
        $roleArn = 'arn:aws:iam::123456789012:role/ComprehendRole';

        $this->comprehendClient->shouldReceive('startSentimentDetectionJob')
            ->once()
            ->with(Mockery::on(function ($args) use ($inputConfig, $outputConfig, $roleArn) {
                return $args['InputDataConfig'] === $inputConfig &&
                       $args['OutputDataConfig'] === $outputConfig &&
                       $args['DataAccessRoleArn'] === $roleArn &&
                       $args['LanguageCode'] === 'en' &&
                       str_starts_with($args['JobName'], 'sentiment-detection-');
            }))
            ->andReturn([
                'JobId' => 'job-sentiment-456',
                'JobArn' => 'arn:aws:comprehend:us-east-1:123456789012:sentiment-detection-job/job-sentiment-456',
                'JobStatus' => 'SUBMITTED',
            ]);

        $jobId = $this->comprehendService->startSentimentDetectionJob(
            $inputConfig,
            $outputConfig,
            $roleArn
        );

        expect($jobId)->toBe('job-sentiment-456');
    });

    test('describes entities detection job successfully', function () {
        $jobId = 'job-entities-123';

        $this->comprehendClient->shouldReceive('describeEntitiesDetectionJob')
            ->once()
            ->with(['JobId' => $jobId])
            ->andReturn([
                'EntitiesDetectionJobProperties' => [
                    'JobId' => $jobId,
                    'JobStatus' => 'COMPLETED',
                    'SubmitTime' => '2025-01-01T10:00:00Z',
                    'EndTime' => '2025-01-01T10:05:00Z',
                    'OutputDataConfig' => ['S3Uri' => 's3://bucket/output/'],
                ],
            ]);

        $result = $this->comprehendService->describeEntitiesDetectionJob($jobId);

        expect($result)->toBeArray();
        expect($result['JobId'])->toBe($jobId);
        expect($result['JobStatus'])->toBe('COMPLETED');
    });

    test('describes sentiment detection job successfully', function () {
        $jobId = 'job-sentiment-456';

        $this->comprehendClient->shouldReceive('describeSentimentDetectionJob')
            ->once()
            ->with(['JobId' => $jobId])
            ->andReturn([
                'SentimentDetectionJobProperties' => [
                    'JobId' => $jobId,
                    'JobStatus' => 'IN_PROGRESS',
                    'SubmitTime' => '2025-01-01T10:00:00Z',
                ],
            ]);

        $result = $this->comprehendService->describeSentimentDetectionJob($jobId);

        expect($result)->toBeArray();
        expect($result['JobId'])->toBe($jobId);
        expect($result['JobStatus'])->toBe('IN_PROGRESS');
    });

    test('handles InvalidRequestException for entities detection job', function () {
        $inputConfig = ['S3Uri' => 's3://bucket/input/'];
        $outputConfig = ['S3Uri' => 's3://bucket/output/'];
        $roleArn = 'invalid-role-arn';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('InvalidRequestException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Invalid role ARN');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Invalid role ARN');
        $awsException->shouldReceive('getCode')
            ->andReturn(400);

        $this->comprehendClient->shouldReceive('startEntitiesDetectionJob')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($inputConfig, $outputConfig, $roleArn) {
            $this->comprehendService->startEntitiesDetectionJob($inputConfig, $outputConfig, $roleArn);
        })->toThrow(TextAnalysisException::class, 'Invalid request parameters for batch entities detection.');
    });

    test('handles generic error for starting entities detection job', function () {
        $inputConfig = ['S3Uri' => 's3://bucket/input/'];
        $outputConfig = ['S3Uri' => 's3://bucket/output/'];
        $roleArn = 'arn:aws:iam::123456789012:role/ComprehendRole';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('ThrottlingException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Too many requests');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Too many requests');
        $awsException->shouldReceive('getCode')
            ->andReturn(429);

        $this->comprehendClient->shouldReceive('startEntitiesDetectionJob')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($inputConfig, $outputConfig, $roleArn) {
            $this->comprehendService->startEntitiesDetectionJob($inputConfig, $outputConfig, $roleArn);
        })->toThrow(TextAnalysisException::class, 'Failed to start entities detection job: Too many requests');
    });

    test('handles generic error for starting sentiment detection job', function () {
        $inputConfig = ['S3Uri' => 's3://bucket/input/'];
        $outputConfig = ['S3Uri' => 's3://bucket/output/'];
        $roleArn = 'arn:aws:iam::123456789012:role/ComprehendRole';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('InternalServerError');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Internal error');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Internal error');
        $awsException->shouldReceive('getCode')
            ->andReturn(500);

        $this->comprehendClient->shouldReceive('startSentimentDetectionJob')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($inputConfig, $outputConfig, $roleArn) {
            $this->comprehendService->startSentimentDetectionJob($inputConfig, $outputConfig, $roleArn);
        })->toThrow(TextAnalysisException::class, 'Failed to start sentiment detection job: Internal error');
    });

    test('handles job not found exception for entities detection', function () {
        $jobId = 'non-existent-job';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('ResourceNotFoundException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Job not found');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Job not found');
        $awsException->shouldReceive('getCode')
            ->andReturn(404);

        $this->comprehendClient->shouldReceive('describeEntitiesDetectionJob')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($jobId) {
            $this->comprehendService->describeEntitiesDetectionJob($jobId);
        })->toThrow(TextAnalysisException::class, "Entities detection job 'non-existent-job' not found.");
    });

    test('handles generic error for describing entities detection job', function () {
        $jobId = 'job-123';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('AccessDeniedException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Access denied');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Access denied');
        $awsException->shouldReceive('getCode')
            ->andReturn(403);

        $this->comprehendClient->shouldReceive('describeEntitiesDetectionJob')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($jobId) {
            $this->comprehendService->describeEntitiesDetectionJob($jobId);
        })->toThrow(TextAnalysisException::class, 'Failed to describe entities detection job: Access denied');
    });

    test('handles job not found exception for sentiment detection', function () {
        $jobId = 'non-existent-sentiment-job';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('ResourceNotFoundException');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Job not found');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Job not found');
        $awsException->shouldReceive('getCode')
            ->andReturn(404);

        $this->comprehendClient->shouldReceive('describeSentimentDetectionJob')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($jobId) {
            $this->comprehendService->describeSentimentDetectionJob($jobId);
        })->toThrow(TextAnalysisException::class, "Sentiment detection job 'non-existent-sentiment-job' not found.");
    });

    test('handles generic error for describing sentiment detection job', function () {
        $jobId = 'job-456';

        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorCode')
            ->andReturn('ServiceUnavailable');
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Service unavailable');
        $awsException->shouldReceive('getMessage')
            ->andReturn('Service unavailable');
        $awsException->shouldReceive('getCode')
            ->andReturn(503);

        $this->comprehendClient->shouldReceive('describeSentimentDetectionJob')
            ->once()
            ->andThrow($awsException);

        expect(function () use ($jobId) {
            $this->comprehendService->describeSentimentDetectionJob($jobId);
        })->toThrow(TextAnalysisException::class, 'Failed to describe sentiment detection job: Service unavailable');
    });
});

describe('Text Truncation', function () {
    test('truncates text that exceeds byte limit', function () {
        // Create a text that's definitely over 5000 bytes
        $longText = str_repeat('This is a test sentence. ', 300); // About 7500 bytes

        $mockResult = new Result([
            'Sentiment' => 'NEUTRAL',
            'SentimentScore' => ['Neutral' => 0.9],
        ]);

        $this->comprehendClient->shouldReceive('detectSentiment')
            ->once()
            ->with(Mockery::on(function ($args) use ($longText) {
                // Check that text was truncated (less than original)
                return strlen($args['Text']) < strlen($longText) &&
                       $args['LanguageCode'] === 'en';
            }))
            ->andReturn($mockResult);

        $result = $this->comprehendService->detectSentiment($longText);

        expect($result['Sentiment'])->toBe('NEUTRAL');
    });
});
