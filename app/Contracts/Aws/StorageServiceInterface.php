<?php

namespace App\Contracts\Aws;

use Illuminate\Http\UploadedFile;

interface StorageServiceInterface
{
    public function uploadFile(UploadedFile $file, string $path, array $metadata = []): string;

    public function deleteFile(string $key): bool;

    public function fileExists(string $key): bool;

    public function getFileUrl(string $key): string;

    public function getSignedUrl(string $key, int $expirationMinutes = 60): string;

    public function copyFile(string $sourceKey, string $destinationKey): bool;

    public function getFileMetadata(string $key): ?array;
}