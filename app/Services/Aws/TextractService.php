<?php

namespace App\Services\Aws;

use App\Contracts\Aws\DocumentAnalysisServiceInterface;
use App\Exceptions\Aws\DocumentAnalysisException;
use Aws\Exception\AwsException;
use Aws\Textract\TextractClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class TextractService implements DocumentAnalysisServiceInterface
{
    public function __construct(
        private TextractClient $textractClient,
        private LoggerInterface $logger = new NullLogger
    ) {}

    /**
     * Start asynchronous document text detection
     */
    public function startDocumentTextDetection(string $s3Key, string $s3Bucket): string
    {
        try {
            $result = $this->textractClient->startDocumentTextDetection([
                'DocumentLocation' => [
                    'S3Object' => [
                        'Bucket' => $s3Bucket,
                        'Name' => $s3Key,
                    ],
                ],
                'NotificationChannel' => [
                    'RoleArn' => config('services.aws.textract.notification_role_arn'),
                    'SNSTopicArn' => config('services.aws.textract.sns_topic_arn'),
                ],
            ]);

            $this->logger->info('Started Textract text detection job', [
                's3_key' => $s3Key,
                'job_id' => $result['JobId'],
            ]);

            return $result['JobId'];
        } catch (AwsException $e) {
            $this->logger->error('Failed to start Textract text detection', [
                's3_key' => $s3Key,
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            throw new DocumentAnalysisException(
                'Failed to start document text detection: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get results of asynchronous text detection
     */
    public function getDocumentTextDetectionResults(string $jobId): ?array
    {
        try {
            $result = $this->textractClient->getDocumentTextDetection([
                'JobId' => $jobId,
            ]);

            if ($result['JobStatus'] === 'SUCCEEDED') {
                $this->logger->info('Retrieved Textract text detection results', [
                    'job_id' => $jobId,
                    'blocks_count' => count($result['Blocks'] ?? []),
                ]);

                return $this->aggregateAllPages($result, 'getDocumentTextDetection', $jobId);
            }

            if ($result['JobStatus'] === 'FAILED') {
                $this->logger->error('Textract text detection job failed', [
                    'job_id' => $jobId,
                    'status_message' => $result['StatusMessage'] ?? 'Unknown error',
                ]);

                throw new DocumentAnalysisException(
                    'Text detection job failed: '.($result['StatusMessage'] ?? 'Unknown error')
                );
            }

            if ($result['JobStatus'] === 'PARTIAL_SUCCESS') {
                $this->logger->warning('Textract text detection partially succeeded', [
                    'job_id' => $jobId,
                    'status_message' => $result['StatusMessage'] ?? 'Some pages could not be processed',
                    'warnings' => $result['Warnings'] ?? [],
                ]);

                // Return the partial results with a flag
                $partialResults = $this->aggregateAllPages($result, 'getDocumentTextDetection', $jobId);
                $partialResults['IsPartial'] = true;
                $partialResults['StatusMessage'] = $result['StatusMessage'] ?? 'Some pages could not be processed';
                $partialResults['Warnings'] = $result['Warnings'] ?? [];

                return $partialResults;
            }

            // Job is still IN_PROGRESS
            return null;
        } catch (AwsException $e) {
            $this->logger->error('Failed to get Textract text detection results', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            throw new DocumentAnalysisException(
                'Failed to get text detection results: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Synchronous document text detection
     */
    public function detectDocumentText(string $s3Key, string $s3Bucket): array
    {
        try {
            $result = $this->textractClient->detectDocumentText([
                'Document' => [
                    'S3Object' => [
                        'Bucket' => $s3Bucket,
                        'Name' => $s3Key,
                    ],
                ],
            ]);

            $this->logger->info('Completed synchronous Textract text detection', [
                's3_key' => $s3Key,
                'blocks_count' => count($result['Blocks'] ?? []),
            ]);

            return $result->toArray();
        } catch (AwsException $e) {
            $this->logger->error('Failed to detect document text', [
                's3_key' => $s3Key,
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            if ($e->getAwsErrorCode() === 'ProvisionedThroughputExceededException') {
                throw new DocumentAnalysisException(
                    'Textract rate limit exceeded. Please try again later.',
                    429,
                    $e
                );
            }

            if ($e->getAwsErrorCode() === 'InvalidS3ObjectException') {
                throw new DocumentAnalysisException(
                    'Invalid document format or corrupted file.',
                    400,
                    $e
                );
            }

            if ($e->getAwsErrorCode() === 'DocumentTooLargeException') {
                throw new DocumentAnalysisException(
                    'Document is too large for synchronous processing. Maximum size is 5MB.',
                    413,
                    $e
                );
            }

            throw new DocumentAnalysisException(
                'Failed to detect document text: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Analyze document for forms and tables
     */
    public function analyzeDocument(string $s3Key, string $s3Bucket, array $featureTypes = ['FORMS', 'TABLES']): array
    {
        try {
            $result = $this->textractClient->analyzeDocument([
                'Document' => [
                    'S3Object' => [
                        'Bucket' => $s3Bucket,
                        'Name' => $s3Key,
                    ],
                ],
                'FeatureTypes' => $featureTypes,
            ]);

            $this->logger->info('Completed Textract document analysis', [
                's3_key' => $s3Key,
                'feature_types' => $featureTypes,
                'blocks_count' => count($result['Blocks'] ?? []),
            ]);

            return $result->toArray();
        } catch (AwsException $e) {
            $this->logger->error('Failed to analyze document', [
                's3_key' => $s3Key,
                'feature_types' => $featureTypes,
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            if ($e->getAwsErrorCode() === 'UnsupportedDocumentException') {
                throw new DocumentAnalysisException(
                    'Document format not supported for analysis.',
                    415,
                    $e
                );
            }

            throw new DocumentAnalysisException(
                'Failed to analyze document: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Start asynchronous document analysis
     */
    public function startDocumentAnalysis(string $s3Key, string $s3Bucket, array $featureTypes = ['FORMS', 'TABLES']): string
    {
        try {
            $result = $this->textractClient->startDocumentAnalysis([
                'DocumentLocation' => [
                    'S3Object' => [
                        'Bucket' => $s3Bucket,
                        'Name' => $s3Key,
                    ],
                ],
                'FeatureTypes' => $featureTypes,
                'NotificationChannel' => [
                    'RoleArn' => config('services.aws.textract.notification_role_arn'),
                    'SNSTopicArn' => config('services.aws.textract.sns_topic_arn'),
                ],
            ]);

            $this->logger->info('Started Textract document analysis job', [
                's3_key' => $s3Key,
                'feature_types' => $featureTypes,
                'job_id' => $result['JobId'],
            ]);

            return $result['JobId'];
        } catch (AwsException $e) {
            $this->logger->error('Failed to start document analysis', [
                's3_key' => $s3Key,
                'feature_types' => $featureTypes,
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            throw new DocumentAnalysisException(
                'Failed to start document analysis: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get results of asynchronous document analysis
     */
    public function getDocumentAnalysisResults(string $jobId): ?array
    {
        try {
            $result = $this->textractClient->getDocumentAnalysis([
                'JobId' => $jobId,
            ]);

            if ($result['JobStatus'] === 'SUCCEEDED') {
                $this->logger->info('Retrieved Textract analysis results', [
                    'job_id' => $jobId,
                    'blocks_count' => count($result['Blocks'] ?? []),
                ]);

                return $this->aggregateAllPages($result, 'getDocumentAnalysis', $jobId);
            }

            if ($result['JobStatus'] === 'FAILED') {
                $this->logger->error('Textract analysis job failed', [
                    'job_id' => $jobId,
                    'status_message' => $result['StatusMessage'] ?? 'Unknown error',
                ]);

                throw new DocumentAnalysisException(
                    'Document analysis job failed: '.($result['StatusMessage'] ?? 'Unknown error')
                );
            }

            if ($result['JobStatus'] === 'PARTIAL_SUCCESS') {
                $this->logger->warning('Textract analysis partially succeeded', [
                    'job_id' => $jobId,
                    'status_message' => $result['StatusMessage'] ?? 'Some pages could not be analyzed',
                    'warnings' => $result['Warnings'] ?? [],
                ]);

                // Return the partial results with a flag
                $partialResults = $this->aggregateAllPages($result, 'getDocumentAnalysis', $jobId);
                $partialResults['IsPartial'] = true;
                $partialResults['StatusMessage'] = $result['StatusMessage'] ?? 'Some pages could not be analyzed';
                $partialResults['Warnings'] = $result['Warnings'] ?? [];

                return $partialResults;
            }

            // Job is still IN_PROGRESS
            return null;
        } catch (AwsException $e) {
            $this->logger->error('Failed to get document analysis results', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            throw new DocumentAnalysisException(
                'Failed to get document analysis results: '.$e->getAwsErrorMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Aggregate results from all pages for multi-page documents
     */
    private function aggregateAllPages(array $initialResult, string $method, string $jobId): array
    {
        $allBlocks = $initialResult['Blocks'] ?? [];
        $nextToken = $initialResult['NextToken'] ?? null;

        while ($nextToken) {
            $nextResult = $this->textractClient->$method([
                'JobId' => $jobId,
                'NextToken' => $nextToken,
            ]);

            $allBlocks = array_merge($allBlocks, $nextResult['Blocks'] ?? []);
            $nextToken = $nextResult['NextToken'] ?? null;
        }

        $initialResult['Blocks'] = $allBlocks;
        unset($initialResult['NextToken']);

        return $initialResult;
    }
}
