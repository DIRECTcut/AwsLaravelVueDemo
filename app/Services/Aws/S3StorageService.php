<?php

namespace App\Services\Aws;

use App\Contracts\Aws\StorageServiceInterface;
use App\Exceptions\Aws\StorageException;
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class S3StorageService implements StorageServiceInterface
{
    public function __construct(
        private S3Client $s3Client,
        private string $bucket
    ) {}

    public function uploadFile(UploadedFile $file, string $path, array $metadata = []): string
    {
        if (! $file->isValid()) {
            throw StorageException::invalidFile('File upload failed or file is corrupted');
        }

        $filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $key = $path.'/'.Str::uuid().'.'.$extension;

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => fopen($file->getRealPath(), 'r'),
                'ContentType' => $file->getMimeType(),
                'Metadata' => $metadata,
            ]);

            return $key;
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() === 'AccessDenied') {
                throw StorageException::accessDenied('upload', $key);
            }
            throw StorageException::uploadFailed($key, $e->getMessage());
        } catch (AwsException $e) {
            throw StorageException::uploadFailed($key, $e->getMessage());
        }
    }

    public function deleteFile(string $key): bool
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return true;
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() === 'AccessDenied') {
                throw StorageException::accessDenied('delete', $key);
            }
            throw StorageException::deleteFailed($key, $e->getMessage());
        } catch (AwsException $e) {
            throw StorageException::deleteFailed($key, $e->getMessage());
        }
    }

    public function fileExists(string $key): bool
    {
        return $this->s3Client->doesObjectExist($this->bucket, $key);
    }

    public function getFileUrl(string $key): string
    {
        return $this->s3Client->getObjectUrl($this->bucket, $key);
    }

    public function getSignedUrl(string $key, int $expirationMinutes = 60): string
    {
        $command = $this->s3Client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        $request = $this->s3Client->createPresignedRequest($command, "+{$expirationMinutes} minutes");

        return (string) $request->getUri();
    }

    public function copyFile(string $sourceKey, string $destinationKey): bool
    {
        try {
            $this->s3Client->copyObject([
                'Bucket' => $this->bucket,
                'CopySource' => $this->bucket.'/'.$sourceKey,
                'Key' => $destinationKey,
            ]);

            return true;
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() === 'NoSuchKey') {
                throw StorageException::fileNotFound($sourceKey);
            }
            if ($e->getAwsErrorCode() === 'AccessDenied') {
                throw StorageException::accessDenied('copy', $sourceKey);
            }
            throw StorageException::copyFailed($sourceKey, $destinationKey, $e->getMessage());
        } catch (AwsException $e) {
            throw StorageException::copyFailed($sourceKey, $destinationKey, $e->getMessage());
        }
    }

    public function getFileMetadata(string $key): ?array
    {
        try {
            $result = $this->s3Client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return $result->toArray();
        } catch (S3Exception $e) {
            if (in_array($e->getAwsErrorCode(), ['NotFound', 'NoSuchKey'])) {
                return null;
            }
            if ($e->getAwsErrorCode() === 'AccessDenied') {
                throw StorageException::accessDenied('metadata', $key);
            }
            throw new StorageException("Failed to get metadata for '{$key}': {$e->getMessage()}");
        } catch (AwsException $e) {
            throw new StorageException("Failed to get metadata for '{$key}': {$e->getMessage()}");
        }
    }
}
