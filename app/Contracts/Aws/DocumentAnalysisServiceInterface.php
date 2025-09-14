<?php

namespace App\Contracts\Aws;

interface DocumentAnalysisServiceInterface
{
    public function startDocumentTextDetection(string $s3Key, string $s3Bucket): string;

    public function getDocumentTextDetectionResults(string $jobId): ?array;

    public function detectDocumentText(string $s3Key, string $s3Bucket): array;

    public function analyzeDocument(string $s3Key, string $s3Bucket, array $featureTypes = ['FORMS', 'TABLES']): array;

    public function startDocumentAnalysis(string $s3Key, string $s3Bucket, array $featureTypes = ['FORMS', 'TABLES']): string;

    public function getDocumentAnalysisResults(string $jobId): ?array;
}
