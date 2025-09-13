<?php

namespace App\Contracts\Aws;

interface TextAnalysisServiceInterface
{
    public function detectSentiment(string $text, string $languageCode = 'en'): array;

    public function detectEntities(string $text, string $languageCode = 'en'): array;

    public function detectKeyPhrases(string $text, string $languageCode = 'en'): array;

    public function detectLanguage(string $text): array;

    public function startEntitiesDetectionJob(array $inputDataConfig, array $outputDataConfig, string $dataAccessRoleArn, string $languageCode = 'en'): string;

    public function startSentimentDetectionJob(array $inputDataConfig, array $outputDataConfig, string $dataAccessRoleArn, string $languageCode = 'en'): string;

    public function describeEntitiesDetectionJob(string $jobId): array;

    public function describeSentimentDetectionJob(string $jobId): array;
}