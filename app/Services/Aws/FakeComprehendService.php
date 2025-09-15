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
            'Sentiment' => 'POSITIVE',
            'SentimentScore' => [
                'Positive' => 0.75,
                'Negative' => 0.10,
                'Neutral' => 0.12,
                'Mixed' => 0.03,
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
            'Entities' => [
                [
                    'Text' => 'John Doe',
                    'Type' => 'PERSON',
                    'Score' => 0.98,
                    'BeginOffset' => 0,
                    'EndOffset' => 8,
                ],
                [
                    'Text' => 'Sample Company',
                    'Type' => 'ORGANIZATION',
                    'Score' => 0.95,
                    'BeginOffset' => 20,
                    'EndOffset' => 34,
                ],
                [
                    'Text' => '2025-09-14',
                    'Type' => 'DATE',
                    'Score' => 0.99,
                    'BeginOffset' => 50,
                    'EndOffset' => 60,
                ],
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
            'KeyPhrases' => [
                [
                    'Text' => 'document processing',
                    'Score' => 0.98,
                    'BeginOffset' => 10,
                    'EndOffset' => 29,
                ],
                [
                    'Text' => 'machine learning',
                    'Score' => 0.95,
                    'BeginOffset' => 35,
                    'EndOffset' => 51,
                ],
                [
                    'Text' => 'artificial intelligence',
                    'Score' => 0.92,
                    'BeginOffset' => 60,
                    'EndOffset' => 83,
                ],
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
