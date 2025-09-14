<?php

namespace App\Exceptions\Aws;

use Exception;

class StorageException extends Exception
{
    public static function uploadFailed(string $key, string $reason): self
    {
        return new self("Failed to upload file to S3 key '{$key}': {$reason}");
    }

    public static function deleteFailed(string $key, string $reason): self
    {
        return new self("Failed to delete file from S3 key '{$key}': {$reason}");
    }

    public static function copyFailed(string $sourceKey, string $destinationKey, string $reason): self
    {
        return new self("Failed to copy file from '{$sourceKey}' to '{$destinationKey}': {$reason}");
    }

    public static function fileNotFound(string $key): self
    {
        return new self("File not found at S3 key '{$key}'");
    }

    public static function accessDenied(string $operation, string $key): self
    {
        return new self("Access denied for {$operation} operation on S3 key '{$key}'");
    }

    public static function invalidFile(string $reason): self
    {
        return new self("Invalid file: {$reason}");
    }
}
