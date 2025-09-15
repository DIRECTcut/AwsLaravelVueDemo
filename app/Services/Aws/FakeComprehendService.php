<?php

namespace App\Services\Aws;

use App\Contracts\Aws\TextAnalysisServiceInterface;
use Psr\Log\LoggerInterface;

class FakeComprehendService implements TextAnalysisServiceInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function detectSentiment(string $text, string $languageCode = 'en'): array
    {
        $this->logger->info('[FAKE] Comprehend detectSentiment called', [
            'text_length' => strlen($text),
            'language_code' => $languageCode,
        ]);

        sleep(1);

        return [
            'sentiment' => 'POSITIVE',
            'scores' => [
                'positive' => 0.75,
                'negative' => 0.10,
                'neutral' => 0.12,
                'mixed' => 0.03,
            ],
        ];
    }

    public function detectEntities(string $text, string $languageCode = 'en'): array
    {
        $this->logger->info('[FAKE] Comprehend detectEntities called', [
            'text_length' => strlen($text),
            'language_code' => $languageCode,
        ]);

        sleep(1);

        return [
            [
                'text' => 'John Doe',
                'type' => 'PERSON',
                'score' => 0.98,
                'begin_offset' => 0,
                'end_offset' => 8,
            ],
            [
                'text' => 'Sample Company',
                'type' => 'ORGANIZATION',
                'score' => 0.95,
                'begin_offset' => 20,
                'end_offset' => 34,
            ],
            [
                'text' => '2025-09-14',
                'type' => 'DATE',
                'score' => 0.99,
                'begin_offset' => 50,
                'end_offset' => 60,
            ],
        ];
    }

    public function detectKeyPhrases(string $text, string $languageCode = 'en'): array
    {
        $this->logger->info('[FAKE] Comprehend detectKeyPhrases called', [
            'text_length' => strlen($text),
            'language_code' => $languageCode,
        ]);

        return [
            [
                'text' => 'document processing',
                'score' => 0.98,
                'begin_offset' => 10,
                'end_offset' => 29,
            ],
            [
                'text' => 'machine learning',
                'score' => 0.95,
                'begin_offset' => 35,
                'end_offset' => 51,
            ],
            [
                'text' => 'artificial intelligence',
                'score' => 0.92,
                'begin_offset' => 60,
                'end_offset' => 83,
            ],
        ];
    }

    public function detectLanguage(string $text): array
    {
        $this->logger->info('[FAKE] Comprehend detectLanguage called', [
            'text_length' => strlen($text),
        ]);

        return [
            'languages' => [
                [
                    'language_code' => 'en',
                    'score' => 0.99,
                ],
                [
                    'language_code' => 'es',
                    'score' => 0.005,
                ],
            ],
        ];
    }

    public function startEntitiesDetectionJob(array $inputDataConfig, array $outputDataConfig, string $dataAccessRoleArn, string $languageCode = 'en'): string
    {
        $jobId = 'fake-entities-job-'.uniqid();

        $this->logger->info('[FAKE] Comprehend entities job started', [
            'job_id' => $jobId,
            'language_code' => $languageCode,
        ]);

        return $jobId;
    }

    public function startSentimentDetectionJob(array $inputDataConfig, array $outputDataConfig, string $dataAccessRoleArn, string $languageCode = 'en'): string
    {
        $jobId = 'fake-sentiment-job-'.uniqid();

        $this->logger->info('[FAKE] Comprehend sentiment job started', [
            'job_id' => $jobId,
            'language_code' => $languageCode,
        ]);

        return $jobId;
    }

    public function describeEntitiesDetectionJob(string $jobId): array
    {
        $this->logger->info('[FAKE] Comprehend entities job checked', ['job_id' => $jobId]);

        return [
            'status' => 'COMPLETED',
            'entities' => $this->detectEntities('fake text'),
        ];
    }

    public function describeSentimentDetectionJob(string $jobId): array
    {
        $this->logger->info('[FAKE] Comprehend sentiment job checked', ['job_id' => $jobId]);

        return [
            'status' => 'COMPLETED',
            'sentiment' => $this->detectSentiment('fake text'),
        ];
    }
}
