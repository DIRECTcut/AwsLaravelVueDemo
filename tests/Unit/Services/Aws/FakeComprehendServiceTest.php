<?php

use App\Contracts\Aws\TextAnalysisServiceInterface;
use App\Services\Aws\FakeComprehendService;
use Mockery;
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
        expect($this->service)->toBeInstanceOf(TextAnalysisServiceInterface::class);
    });
});

describe('Sentiment Detection', function () {
    test('detectSentiment returns valid structure', function () {
        $this->logger->shouldReceive('info')->once();

        $result = $this->service->detectSentiment('Sample text');

        expect($result)->toBeArray()
            ->toHaveKey('sentiment', 'POSITIVE')
            ->toHaveKey('scores');

        expect($result['scores'])->toBeArray()
            ->toHaveKeys(['positive', 'negative', 'neutral', 'mixed']);

        // Verify scores are valid probabilities
        expect($result['scores']['positive'])->toBeFloat()->toBeBetween(0, 1);
        expect($result['scores']['negative'])->toBeFloat()->toBeBetween(0, 1);
        expect($result['scores']['neutral'])->toBeFloat()->toBeBetween(0, 1);
        expect($result['scores']['mixed'])->toBeFloat()->toBeBetween(0, 1);
    });

    test('detectSentiment accepts language code parameter', function () {
        $this->logger->shouldReceive('info')->once()->with('[FAKE] Comprehend detectSentiment called', [
            'text_length' => 11,
            'language_code' => 'es',
        ]);

        $result = $this->service->detectSentiment('Sample text', 'es');

        expect($result)->toBeArray()->toHaveKey('sentiment');
    });
});

describe('Entity Detection', function () {
    test('detectEntities returns valid structure', function () {
        $this->logger->shouldReceive('info')->once();

        $result = $this->service->detectEntities('Sample text');

        expect($result)->toBeArray();

        foreach ($result as $entity) {
            expect($entity)->toBeArray()
                ->toHaveKeys(['text', 'type', 'score', 'begin_offset', 'end_offset']);

            expect($entity['text'])->toBeString();
            expect($entity['type'])->toBeString();
            expect($entity['score'])->toBeFloat()->toBeBetween(0, 1);
            expect($entity['begin_offset'])->toBeInt()->toBeGreaterThanOrEqual(0);
            expect($entity['end_offset'])->toBeInt()->toBeGreaterThanOrEqual($entity['begin_offset']);
        }
    });

    test('detectEntities accepts language code parameter', function () {
        $this->logger->shouldReceive('info')->once()->with('[FAKE] Comprehend detectEntities called', [
            'text_length' => 11,
            'language_code' => 'fr',
        ]);

        $result = $this->service->detectEntities('Sample text', 'fr');

        expect($result)->toBeArray();
    });
});

describe('Key Phrase Detection', function () {
    test('detectKeyPhrases returns valid structure', function () {
        $this->logger->shouldReceive('info')->once();

        $result = $this->service->detectKeyPhrases('Sample text');

        expect($result)->toBeArray();

        foreach ($result as $phrase) {
            expect($phrase)->toBeArray()
                ->toHaveKeys(['text', 'score', 'begin_offset', 'end_offset']);

            expect($phrase['text'])->toBeString();
            expect($phrase['score'])->toBeFloat()->toBeBetween(0, 1);
            expect($phrase['begin_offset'])->toBeInt()->toBeGreaterThanOrEqual(0);
            expect($phrase['end_offset'])->toBeInt()->toBeGreaterThanOrEqual($phrase['begin_offset']);
        }
    });

    test('detectKeyPhrases accepts language code parameter', function () {
        $this->logger->shouldReceive('info')->once()->with('[FAKE] Comprehend detectKeyPhrases called', [
            'text_length' => 11,
            'language_code' => 'de',
        ]);

        $result = $this->service->detectKeyPhrases('Sample text', 'de');

        expect($result)->toBeArray();
    });
});

describe('Language Detection', function () {
    test('detectLanguage returns valid structure', function () {
        $this->logger->shouldReceive('info')->once();

        $result = $this->service->detectLanguage('Sample text');

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
        $this->logger->shouldReceive('info')->once();

        $jobId = $this->service->startEntitiesDetectionJob([], [], 'arn:aws:iam::123:role/test');

        expect($jobId)->toBeString()
            ->toStartWith('fake-entities-job-');
    });

    test('startSentimentDetectionJob returns job ID', function () {
        $this->logger->shouldReceive('info')->once();

        $jobId = $this->service->startSentimentDetectionJob([], [], 'arn:aws:iam::123:role/test');

        expect($jobId)->toBeString()
            ->toStartWith('fake-sentiment-job-');
    });

    test('describeEntitiesDetectionJob returns valid structure', function () {
        $this->logger->shouldReceive('info')->twice(); // One for describe, one for detectEntities

        $result = $this->service->describeEntitiesDetectionJob('fake-job-123');

        expect($result)->toBeArray()
            ->toHaveKeys(['status', 'entities']);

        expect($result['status'])->toBe('COMPLETED');
        expect($result['entities'])->toBeArray();
    });

    test('describeSentimentDetectionJob returns valid structure', function () {
        $this->logger->shouldReceive('info')->twice(); // One for describe, one for detectSentiment

        $result = $this->service->describeSentimentDetectionJob('fake-job-123');

        expect($result)->toBeArray()
            ->toHaveKeys(['status', 'sentiment']);

        expect($result['status'])->toBe('COMPLETED');
        expect($result['sentiment'])->toBeArray()->toHaveKey('sentiment');
    });
});

describe('Data Structure Consistency', function () {
    test('returned data matches expected AWS Comprehend format', function () {
        $this->logger->shouldReceive('info')->atLeast()->once();

        // Test sentiment structure matches AWS format
        $sentiment = $this->service->detectSentiment('test');
        expect($sentiment)->toHaveKeys(['sentiment', 'scores']);
        expect($sentiment['sentiment'])->toBeString();
        expect($sentiment['scores'])->toBeArray();

        // Test entities structure matches AWS format
        $entities = $this->service->detectEntities('test');
        expect($entities[0])->toHaveKeys(['text', 'type', 'score', 'begin_offset', 'end_offset']);
        expect($entities[0]['text'])->toBeString();
        expect($entities[0]['type'])->toBeString();
        expect($entities[0]['score'])->toBeFloat();
        expect($entities[0]['begin_offset'])->toBeInt();
        expect($entities[0]['end_offset'])->toBeInt();

        // Test key phrases structure matches AWS format
        $phrases = $this->service->detectKeyPhrases('test');
        expect($phrases[0])->toHaveKeys(['text', 'score', 'begin_offset', 'end_offset']);
        expect($phrases[0]['text'])->toBeString();
        expect($phrases[0]['score'])->toBeFloat();
        expect($phrases[0]['begin_offset'])->toBeInt();
        expect($phrases[0]['end_offset'])->toBeInt();

        // Test language detection structure matches AWS format
        $languages = $this->service->detectLanguage('test');
        expect($languages)->toHaveKey('languages');
        expect($languages['languages'][0])->toHaveKeys(['language_code', 'score']);
        expect($languages['languages'][0]['language_code'])->toBeString();
        expect($languages['languages'][0]['score'])->toBeFloat();
    });
});
