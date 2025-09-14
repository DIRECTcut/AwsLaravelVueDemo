<?php

namespace App\Services\Aws;

use App\Contracts\Aws\TextAnalysisServiceInterface;
use App\Exceptions\Aws\TextAnalysisException;
use Aws\Comprehend\ComprehendClient;
use Aws\Exception\AwsException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ComprehendService implements TextAnalysisServiceInterface
{
    public function __construct(
        private ComprehendClient $comprehendClient,
        private LoggerInterface $logger = new NullLogger
    ) {}

    /**
     * Detect sentiment in text
     */
    public function detectSentiment(string $text, string $languageCode = 'en'): array
    {
        try {
            $result = $this->comprehendClient->detectSentiment([
                'Text' => $this->truncateText($text),
                'LanguageCode' => $languageCode,
            ]);

            $this->logger->info('Detected sentiment', [
                'sentiment' => $result['Sentiment'],
                'scores' => $result['SentimentScore'],
                'language' => $languageCode,
            ]);

            return $result->toArray();
        } catch (AwsException $e) {
            $this->logger->error('Failed to detect sentiment', [
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            if ($e->getAwsErrorCode() === 'TextSizeLimitExceededException') {
                throw new TextAnalysisException(
                    'Text is too large for sentiment analysis. Maximum size is 5000 UTF-8 bytes.',
                    413,
                    $e
                );
            }

            if ($e->getAwsErrorCode() === 'UnsupportedLanguageException') {
                throw new TextAnalysisException(
                    "Language '{$languageCode}' is not supported for sentiment analysis.",
                    400,
                    $e
                );
            }

            throw new TextAnalysisException(
                'Failed to detect sentiment: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Detect entities in text
     */
    public function detectEntities(string $text, string $languageCode = 'en'): array
    {
        try {
            $result = $this->comprehendClient->detectEntities([
                'Text' => $this->truncateText($text),
                'LanguageCode' => $languageCode,
            ]);

            $this->logger->info('Detected entities', [
                'entity_count' => count($result['Entities'] ?? []),
                'language' => $languageCode,
            ]);

            return $result->toArray();
        } catch (AwsException $e) {
            $this->logger->error('Failed to detect entities', [
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            if ($e->getAwsErrorCode() === 'TextSizeLimitExceededException') {
                throw new TextAnalysisException(
                    'Text is too large for entity detection. Maximum size is 100 KB for UTF-8 encoded characters.',
                    413,
                    $e
                );
            }

            throw new TextAnalysisException(
                'Failed to detect entities: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Detect key phrases in text
     */
    public function detectKeyPhrases(string $text, string $languageCode = 'en'): array
    {
        try {
            $result = $this->comprehendClient->detectKeyPhrases([
                'Text' => $this->truncateText($text),
                'LanguageCode' => $languageCode,
            ]);

            $this->logger->info('Detected key phrases', [
                'phrase_count' => count($result['KeyPhrases'] ?? []),
                'language' => $languageCode,
            ]);

            return $result->toArray();
        } catch (AwsException $e) {
            $this->logger->error('Failed to detect key phrases', [
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            throw new TextAnalysisException(
                'Failed to detect key phrases: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Detect dominant language
     */
    public function detectLanguage(string $text): array
    {
        try {
            $result = $this->comprehendClient->detectDominantLanguage([
                'Text' => $this->truncateText($text),
            ]);

            $languages = $result['Languages'] ?? [];
            if (! empty($languages)) {
                $this->logger->info('Detected languages', [
                    'primary_language' => $languages[0]['LanguageCode'] ?? 'unknown',
                    'confidence' => $languages[0]['Score'] ?? 0,
                    'total_detected' => count($languages),
                ]);
            }

            return $result->toArray();
        } catch (AwsException $e) {
            $this->logger->error('Failed to detect language', [
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            throw new TextAnalysisException(
                'Failed to detect language: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Start batch entity detection job
     */
    public function startEntitiesDetectionJob(
        array $inputDataConfig,
        array $outputDataConfig,
        string $dataAccessRoleArn,
        string $languageCode = 'en'
    ): string {
        try {
            $result = $this->comprehendClient->startEntitiesDetectionJob([
                'InputDataConfig' => $inputDataConfig,
                'OutputDataConfig' => $outputDataConfig,
                'DataAccessRoleArn' => $dataAccessRoleArn,
                'LanguageCode' => $languageCode,
                'JobName' => 'entities-detection-'.time(),
            ]);

            $this->logger->info('Started entities detection job', [
                'job_id' => $result['JobId'],
                'job_arn' => $result['JobArn'] ?? null,
            ]);

            return $result['JobId'];
        } catch (AwsException $e) {
            $this->logger->error('Failed to start entities detection job', [
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            if ($e->getAwsErrorCode() === 'InvalidRequestException') {
                throw new TextAnalysisException(
                    'Invalid request parameters for batch entities detection.',
                    400,
                    $e
                );
            }

            throw new TextAnalysisException(
                'Failed to start entities detection job: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Start batch sentiment detection job
     */
    public function startSentimentDetectionJob(
        array $inputDataConfig,
        array $outputDataConfig,
        string $dataAccessRoleArn,
        string $languageCode = 'en'
    ): string {
        try {
            $result = $this->comprehendClient->startSentimentDetectionJob([
                'InputDataConfig' => $inputDataConfig,
                'OutputDataConfig' => $outputDataConfig,
                'DataAccessRoleArn' => $dataAccessRoleArn,
                'LanguageCode' => $languageCode,
                'JobName' => 'sentiment-detection-'.time(),
            ]);

            $this->logger->info('Started sentiment detection job', [
                'job_id' => $result['JobId'],
                'job_arn' => $result['JobArn'] ?? null,
            ]);

            return $result['JobId'];
        } catch (AwsException $e) {
            $this->logger->error('Failed to start sentiment detection job', [
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            throw new TextAnalysisException(
                'Failed to start sentiment detection job: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get batch entities detection job details
     */
    public function describeEntitiesDetectionJob(string $jobId): array
    {
        try {
            $result = $this->comprehendClient->describeEntitiesDetectionJob([
                'JobId' => $jobId,
            ]);

            $jobDetails = $result['EntitiesDetectionJobProperties'] ?? [];

            $this->logger->info('Retrieved entities detection job details', [
                'job_id' => $jobId,
                'status' => $jobDetails['JobStatus'] ?? 'UNKNOWN',
            ]);

            return $jobDetails;
        } catch (AwsException $e) {
            $this->logger->error('Failed to describe entities detection job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                throw new TextAnalysisException(
                    "Entities detection job '{$jobId}' not found.",
                    404,
                    $e
                );
            }

            throw new TextAnalysisException(
                'Failed to describe entities detection job: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get batch sentiment detection job details
     */
    public function describeSentimentDetectionJob(string $jobId): array
    {
        try {
            $result = $this->comprehendClient->describeSentimentDetectionJob([
                'JobId' => $jobId,
            ]);

            $jobDetails = $result['SentimentDetectionJobProperties'] ?? [];

            $this->logger->info('Retrieved sentiment detection job details', [
                'job_id' => $jobId,
                'status' => $jobDetails['JobStatus'] ?? 'UNKNOWN',
            ]);

            return $jobDetails;
        } catch (AwsException $e) {
            $this->logger->error('Failed to describe sentiment detection job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                throw new TextAnalysisException(
                    "Sentiment detection job '{$jobId}' not found.",
                    404,
                    $e
                );
            }

            throw new TextAnalysisException(
                'Failed to describe sentiment detection job: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Truncate text to Comprehend's limits
     * Comprehend has different limits for different operations:
     * - Sentiment: 5,000 bytes
     * - Entities/Key Phrases: 100,000 bytes (100 KB)
     * - Language detection: 100,000 bytes
     */
    private function truncateText(string $text, int $maxBytes = 5000): string
    {
        $textBytes = strlen(mb_convert_encoding($text, 'UTF-8'));

        if ($textBytes <= $maxBytes) {
            return $text;
        }

        // Truncate by character count (approximate)
        $ratio = $maxBytes / $textBytes;
        $charLimit = (int) (mb_strlen($text) * $ratio * 0.95); // 95% to be safe

        $truncated = mb_substr($text, 0, $charLimit);

        $this->logger->warning('Text truncated for Comprehend analysis', [
            'original_bytes' => $textBytes,
            'max_bytes' => $maxBytes,
            'truncated_chars' => $charLimit,
        ]);

        return $truncated;
    }
}
