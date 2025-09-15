<?php

use App\Contracts\Aws\TextAnalysisServiceInterface;
use App\Services\Aws\FakeComprehendService;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    $this->logger = Mockery::mock(LoggerInterface::class);
    $this->service = new FakeComprehendService($this->logger);
});

afterEach(function () {
    Mockery::close();
});

describe('FakeComprehendService Interface Compliance', function () {
    test('implements TextAnalysisServiceInterface', function () {
        // Arrange - service created in beforeEach

        // Act & Assert
        expect($this->service)->toBeInstanceOf(TextAnalysisServiceInterface::class);
    });
});

describe('Sentiment Detection', function () {
    test('detectSentiment returns valid structure', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $sampleText = 'Sample text';

        // Act
        $result = $this->service->detectSentiment($sampleText);

        // Assert
        expect($result)->toBeArray()
            ->toHaveKey('Sentiment', 'POSITIVE')
            ->toHaveKey('SentimentScore');

        expect($result['SentimentScore'])->toBeArray()
            ->toHaveKeys(['Positive', 'Negative', 'Neutral', 'Mixed']);

        // Verify scores are valid probabilities
        expect($result['SentimentScore']['Positive'])->toBeFloat()->toBeBetween(0, 1);
        expect($result['SentimentScore']['Negative'])->toBeFloat()->toBeBetween(0, 1);
        expect($result['SentimentScore']['Neutral'])->toBeFloat()->toBeBetween(0, 1);
        expect($result['SentimentScore']['Mixed'])->toBeFloat()->toBeBetween(0, 1);
    });

    test('detectSentiment accepts language code parameter', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once()->with('[FAKE] Comprehend detectSentiment called', [
            'text_length' => 11,
            'language_code' => 'es',
        ]);
        $sampleText = 'Sample text';
        $languageCode = 'es';

        // Act
        $result = $this->service->detectSentiment($sampleText, $languageCode);

        // Assert
        expect($result)->toBeArray()->toHaveKey('Sentiment');
    });
});

describe('Entity Detection', function () {
    test('detectEntities returns valid structure', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $sampleText = 'Sample text';

        // Act
        $result = $this->service->detectEntities($sampleText);

        // Assert
        expect($result)->toBeArray();

        foreach ($result['Entities'] as $entity) {
            expect($entity)->toBeArray()
                ->toHaveKeys(['Text', 'Type', 'Score', 'BeginOffset', 'EndOffset']);

            expect($entity['Text'])->toBeString();
            expect($entity['Type'])->toBeString();
            expect($entity['Score'])->toBeFloat()->toBeBetween(0, 1);
            expect($entity['BeginOffset'])->toBeInt()->toBeGreaterThanOrEqual(0);
            expect($entity['EndOffset'])->toBeInt()->toBeGreaterThanOrEqual($entity['BeginOffset']);
        }
    });

    test('detectEntities accepts language code parameter', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once()->with('[FAKE] Comprehend detectEntities called', [
            'text_length' => 11,
            'language_code' => 'fr',
        ]);
        $sampleText = 'Sample text';
        $languageCode = 'fr';

        // Act
        $result = $this->service->detectEntities($sampleText, $languageCode);

        // Assert
        expect($result)->toBeArray();
    });
});

describe('Key Phrase Detection', function () {
    test('detectKeyPhrases returns valid structure', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $sampleText = 'Sample text';

        // Act
        $result = $this->service->detectKeyPhrases($sampleText);

        // Assert
        expect($result)->toBeArray();

        foreach ($result['KeyPhrases'] as $phrase) {
            expect($phrase)->toBeArray()
                ->toHaveKeys(['Text', 'Score', 'BeginOffset', 'EndOffset']);

            expect($phrase['Text'])->toBeString();
            expect($phrase['Score'])->toBeFloat()->toBeBetween(0, 1);
            expect($phrase['BeginOffset'])->toBeInt()->toBeGreaterThanOrEqual(0);
            expect($phrase['EndOffset'])->toBeInt()->toBeGreaterThanOrEqual($phrase['BeginOffset']);
        }
    });

    test('detectKeyPhrases accepts language code parameter', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once()->with('[FAKE] Comprehend detectKeyPhrases called', [
            'text_length' => 11,
            'language_code' => 'de',
        ]);
        $sampleText = 'Sample text';
        $languageCode = 'de';

        // Act
        $result = $this->service->detectKeyPhrases($sampleText, $languageCode);

        // Assert
        expect($result)->toBeArray();
    });
});

describe('Language Detection', function () {
    test('detectLanguage returns valid structure', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $sampleText = 'Sample text';

        // Act
        $result = $this->service->detectLanguage($sampleText);

        // Assert
        expect($result)->toBeArray()
            ->toHaveKey('languages');

        expect($result['languages'])->toBeArray();

        foreach ($result['languages'] as $language) {
            expect($language)->toBeArray()
                ->toHaveKeys(['language_code', 'score']);

            expect($language['language_code'])->toBeString()->toHaveLength(2);
            expect($language['score'])->toBeFloat()->toBeBetween(0, 1);
        }
    });
});

describe('Batch Job Operations', function () {
    test('startEntitiesDetectionJob returns job ID', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $inputList = [];
        $outputList = [];
        $dataAccessRoleArn = 'arn:aws:iam::123:role/test';

        // Act
        $jobId = $this->service->startEntitiesDetectionJob($inputList, $outputList, $dataAccessRoleArn);

        // Assert
        expect($jobId)->toBeString()
            ->toStartWith('fake-entities-job-');
    });

    test('startSentimentDetectionJob returns job ID', function () {
        // Arrange
        $this->logger->shouldReceive('info')->once();
        $inputList = [];
        $outputList = [];
        $dataAccessRoleArn = 'arn:aws:iam::123:role/test';

        // Act
        $jobId = $this->service->startSentimentDetectionJob($inputList, $outputList, $dataAccessRoleArn);

        // Assert
        expect($jobId)->toBeString()
            ->toStartWith('fake-sentiment-job-');
    });

    test('describeEntitiesDetectionJob returns valid structure', function () {
        // Arrange
        $this->logger->shouldReceive('info')->twice(); // One for describe, one for detectEntities
        $jobId = 'fake-job-123';

        // Act
        $result = $this->service->describeEntitiesDetectionJob($jobId);

        // Assert
        expect($result)->toBeArray()
            ->toHaveKeys(['status', 'entities']);

        expect($result['status'])->toBe('COMPLETED');
        expect($result['entities'])->toBeArray()
            ->toHaveKey('Entities');
    });

    test('describeSentimentDetectionJob returns valid structure', function () {
        // Arrange
        $this->logger->shouldReceive('info')->twice(); // One for describe, one for detectSentiment
        $jobId = 'fake-job-123';

        // Act
        $result = $this->service->describeSentimentDetectionJob($jobId);

        // Assert
        expect($result)->toBeArray()
            ->toHaveKeys(['status', 'sentiment']);

        expect($result['status'])->toBe('COMPLETED');
        expect($result['sentiment'])->toBeArray()->toHaveKey('Sentiment');
    });
});

describe('Data Structure Consistency', function () {
    test('returned data matches expected AWS Comprehend format', function () {
        // Arrange
        $this->logger->shouldReceive('info')->atLeast()->once();
        $testText = 'test';

        // Act & Assert - Test sentiment structure matches AWS format
        $sentiment = $this->service->detectSentiment($testText);
        expect($sentiment)->toHaveKeys(['Sentiment', 'SentimentScore']);
        expect($sentiment['Sentiment'])->toBeString();
        expect($sentiment['SentimentScore'])->toBeArray();

        // Act & Assert - Test entities structure matches AWS format
        $entities = $this->service->detectEntities($testText);
        expect($entities['Entities'][0])->toHaveKeys(['Text', 'Type', 'Score', 'BeginOffset', 'EndOffset']);
        expect($entities['Entities'][0]['Text'])->toBeString();
        expect($entities['Entities'][0]['Type'])->toBeString();
        expect($entities['Entities'][0]['Score'])->toBeFloat();
        expect($entities['Entities'][0]['BeginOffset'])->toBeInt();
        expect($entities['Entities'][0]['EndOffset'])->toBeInt();

        // Act & Assert - Test key phrases structure matches AWS format
        $phrases = $this->service->detectKeyPhrases($testText);
        expect($phrases['KeyPhrases'][0])->toHaveKeys(['Text', 'Score', 'BeginOffset', 'EndOffset']);
        expect($phrases['KeyPhrases'][0]['Text'])->toBeString();
        expect($phrases['KeyPhrases'][0]['Score'])->toBeFloat();
        expect($phrases['KeyPhrases'][0]['BeginOffset'])->toBeInt();
        expect($phrases['KeyPhrases'][0]['EndOffset'])->toBeInt();

        // Act & Assert - Test language detection structure matches AWS format
        $languages = $this->service->detectLanguage($testText);
        expect($languages)->toHaveKey('languages');
        expect($languages['languages'][0])->toHaveKeys(['language_code', 'score']);
        expect($languages['languages'][0]['language_code'])->toBeString();
        expect($languages['languages'][0]['score'])->toBeFloat();
    });
});
