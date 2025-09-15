<?php

namespace App\Services\Aws;

use App\Contracts\Aws\DocumentAnalysisServiceInterface;
use Psr\Log\LoggerInterface;

class FakeTextractService implements DocumentAnalysisServiceInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function detectDocumentText(string $s3Key, string $s3Bucket): array
    {
        $this->logger->info('[FAKE] Textract detectDocumentText called', [
            's3_key' => $s3Key,
            's3_bucket' => $s3Bucket,
        ]);

        sleep(2);

        return [
            'Blocks' => [
                [
                    'Text' => 'This is fake extracted text from the document.',
                    'Confidence' => 99.5,
                    'BlockType' => 'LINE',
                ],
                [
                    'Text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                    'Confidence' => 98.7,
                    'BlockType' => 'LINE',
                ],
                [
                    'Text' => 'This simulates OCR text extraction for development.',
                    'Confidence' => 97.3,
                    'BlockType' => 'LINE',
                ],
            ],
        ];
    }

    public function analyzeDocument(string $s3Key, string $s3Bucket, array $featureTypes = ['TABLES', 'FORMS']): array
    {
        $this->logger->info('[FAKE] Textract analyzeDocument called', [
            's3_key' => $s3Key,
            's3_bucket' => $s3Bucket,
            'feature_types' => $featureTypes,
        ]);

        sleep(1);

        $result = [
            'Blocks' => [
                [
                    'Text' => 'Document Analysis Results (Fake)',
                    'Confidence' => 99.2,
                    'BlockType' => 'LINE',
                ],
                [
                    'Text' => 'Extracted text from document analysis.',
                    'Confidence' => 98.8,
                    'BlockType' => 'LINE',
                ],
            ],
        ];

        if (in_array('TABLES', $featureTypes)) {
            $result['tables'] = [
                [
                    'rows' => 3,
                    'columns' => 3,
                    'cells' => [
                        ['text' => 'Header 1', 'row' => 0, 'column' => 0],
                        ['text' => 'Header 2', 'row' => 0, 'column' => 1],
                        ['text' => 'Header 3', 'row' => 0, 'column' => 2],
                        ['text' => 'Data 1', 'row' => 1, 'column' => 0],
                        ['text' => 'Data 2', 'row' => 1, 'column' => 1],
                        ['text' => 'Data 3', 'row' => 1, 'column' => 2],
                    ],
                ],
            ];
        }

        if (in_array('FORMS', $featureTypes)) {
            $result['forms'] = [
                ['key' => 'Name', 'value' => 'John Doe'],
                ['key' => 'Date', 'value' => '2025-09-14'],
                ['key' => 'Amount', 'value' => '$1,234.56'],
            ];
        }

        return $result;
    }

    public function startDocumentTextDetection(string $s3Key, string $s3Bucket): string
    {
        $jobId = 'fake-job-'.uniqid();

        $this->logger->info('[FAKE] Textract async job started', [
            'job_id' => $jobId,
            's3_key' => $s3Key,
            's3_bucket' => $s3Bucket,
        ]);

        return $jobId;
    }

    public function getDocumentTextDetectionResults(string $jobId): ?array
    {
        $this->logger->info('[FAKE] Textract async job checked', ['job_id' => $jobId]);

        // Simulate job completion
        return [
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [
                [
                    'Text' => 'Async text detection complete (Fake).',
                    'Confidence' => 99.0,
                    'BlockType' => 'LINE',
                ],
                [
                    'Text' => 'This is the extracted text from async processing.',
                    'Confidence' => 98.5,
                    'BlockType' => 'LINE',
                ],
            ],
        ];
    }

    public function startDocumentAnalysis(string $s3Key, string $s3Bucket, array $featureTypes = ['TABLES', 'FORMS']): string
    {
        $jobId = 'fake-analysis-'.uniqid();

        $this->logger->info('[FAKE] Textract async analysis started', [
            'job_id' => $jobId,
            's3_key' => $s3Key,
            's3_bucket' => $s3Bucket,
            'feature_types' => $featureTypes,
        ]);

        return $jobId;
    }

    public function getDocumentAnalysisResults(string $jobId): ?array
    {
        $this->logger->info('[FAKE] Textract async analysis checked', ['job_id' => $jobId]);

        return $this->analyzeDocument('fake-key', 'fake-bucket');
    }
}
