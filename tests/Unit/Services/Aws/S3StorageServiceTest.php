<?php

use App\Exceptions\Aws\StorageException;
use App\Services\Aws\S3StorageService;
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->s3Client = Mockery::mock(S3Client::class);
    $this->bucket = 'test-bucket';
    $this->service = new S3StorageService($this->s3Client, $this->bucket);
});

afterEach(function () {
    Mockery::close();
});

describe('Basic Operations', function () {
    test('can upload file to S3', function () {
        // Arrange
        $file = UploadedFile::fake()->create('document.pdf', 1024);
        $path = 'documents/test';
        $metadata = ['user_id' => '123'];

        $this->s3Client->shouldReceive('putObject')
            ->once()
            ->with(Mockery::on(function ($args) use ($path, $metadata) {
                return $args['Bucket'] === $this->bucket &&
                       str_contains($args['Key'], $path) &&
                       $args['Metadata'] === $metadata;
            }))
            ->andReturn(['ObjectURL' => 'https://test-bucket.s3.amazonaws.com/documents/test/document.pdf']);

        // Act
        $result = $this->service->uploadFile($file, $path, $metadata);

        // Assert
        expect($result)->toBeString();
        expect($result)->toContain($path);
    });

    test('can check if file exists', function () {
        // Arrange
        $key = 'documents/test.pdf';

        $this->s3Client->shouldReceive('doesObjectExist')
            ->once()
            ->with($this->bucket, $key)
            ->andReturn(true);

        // Act
        $result = $this->service->fileExists($key);

        // Assert
        expect($result)->toBeTrue();
    });

    test('can delete file from S3', function () {
        // Arrange
        $key = 'documents/test.pdf';

        $this->s3Client->shouldReceive('deleteObject')
            ->once()
            ->with([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ])
            ->andReturn(['DeleteMarker' => true]);

        // Act
        $result = $this->service->deleteFile($key);

        // Assert
        expect($result)->toBeTrue();
    });

    test('can generate signed URL', function () {
        // Arrange
        $key = 'documents/test.pdf';
        $expiration = 60;

        $command = Mockery::mock('Aws\\CommandInterface');
        $request = Mockery::mock();
        $request->shouldReceive('getUri')->andReturn('https://signed-url.com');

        $this->s3Client->shouldReceive('getCommand')
            ->once()
            ->with('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $key,
            ])
            ->andReturn($command);

        $this->s3Client->shouldReceive('createPresignedRequest')
            ->once()
            ->with($command, "+{$expiration} minutes")
            ->andReturn($request);

        // Act
        $result = $this->service->getSignedUrl($key, $expiration);

        // Assert
        expect($result)->toBe('https://signed-url.com');
    });

    test('can get file URL', function () {
        // Arrange
        $key = 'documents/test.pdf';

        $this->s3Client->shouldReceive('getObjectUrl')
            ->once()
            ->with($this->bucket, $key)
            ->andReturn('https://test-bucket.s3.amazonaws.com/documents/test.pdf');

        // Act
        $result = $this->service->getFileUrl($key);

        // Assert
        expect($result)->toBe('https://test-bucket.s3.amazonaws.com/documents/test.pdf');
    });

    test('can get file metadata', function () {
        // Arrange
        $key = 'documents/test.pdf';
        $metadata = ['ContentLength' => 1024, 'ContentType' => 'application/pdf'];

        $result = Mockery::mock();
        $result->shouldReceive('toArray')->andReturn($metadata);

        $this->s3Client->shouldReceive('headObject')
            ->once()
            ->with([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ])
            ->andReturn($result);

        // Act
        $result = $this->service->getFileMetadata($key);

        // Assert
        expect($result)->toBe($metadata);
    });

    test('returns null when file metadata not found', function () {
        // Arrange
        $key = 'documents/nonexistent.pdf';

        $exception = Mockery::mock(S3Exception::class);
        $exception->shouldReceive('getAwsErrorCode')->andReturn('NotFound');

        $this->s3Client->shouldReceive('headObject')
            ->once()
            ->with([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ])
            ->andThrow($exception);

        // Act
        $result = $this->service->getFileMetadata($key);

        // Assert
        expect($result)->toBeNull();
    });

    test('can copy file within S3', function () {
        // Arrange
        $sourceKey = 'documents/source.pdf';
        $destinationKey = 'documents/destination.pdf';

        $this->s3Client->shouldReceive('copyObject')
            ->once()
            ->with([
                'Bucket' => $this->bucket,
                'CopySource' => $this->bucket.'/'.$sourceKey,
                'Key' => $destinationKey,
            ])
            ->andReturn(['CopyObjectResult' => ['ETag' => 'test-etag']]);

        // Act
        $result = $this->service->copyFile($sourceKey, $destinationKey);

        // Assert
        expect($result)->toBeTrue();
    });
});

describe('Error Handling', function () {
    test('throws exception when uploading invalid file', function () {
        // Arrange
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(false);

        // Act & Assert
        expect(fn () => $this->service->uploadFile($file, 'documents'))
            ->toThrow(StorageException::class, 'Invalid file: File upload failed or file is corrupted');
    });

    test('throws access denied exception on upload', function () {
        // Arrange
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $exception = Mockery::mock(S3Exception::class);
        $exception->shouldReceive('getAwsErrorCode')->andReturn('AccessDenied');
        $exception->shouldReceive('getMessage')->andReturn('Access Denied');

        $this->s3Client->shouldReceive('putObject')
            ->once()
            ->andThrow($exception);

        // Act & Assert
        expect(fn () => $this->service->uploadFile($file, 'documents'))
            ->toThrow(StorageException::class, 'Access denied for upload operation');
    });

    test('throws upload failed exception on AWS error', function () {
        // Arrange
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $exception = new AwsException('Network timeout', Mockery::mock('Aws\\Command'));

        $this->s3Client->shouldReceive('putObject')
            ->once()
            ->andThrow($exception);

        // Act & Assert
        expect(fn () => $this->service->uploadFile($file, 'documents'))
            ->toThrow(StorageException::class, 'Failed to upload file');
    });

    test('throws access denied exception on delete', function () {
        // Arrange
        $key = 'documents/test.pdf';

        $exception = Mockery::mock(S3Exception::class);
        $exception->shouldReceive('getAwsErrorCode')->andReturn('AccessDenied');
        $exception->shouldReceive('getMessage')->andReturn('Access Denied');

        $this->s3Client->shouldReceive('deleteObject')
            ->once()
            ->andThrow($exception);

        // Act & Assert
        expect(fn () => $this->service->deleteFile($key))
            ->toThrow(StorageException::class, 'Access denied for delete operation');
    });

    test('throws delete failed exception on S3 error', function () {
        // Arrange
        $key = 'documents/test.pdf';

        $exception = new S3Exception('Internal Error', Mockery::mock('Aws\\Command'));

        $this->s3Client->shouldReceive('deleteObject')
            ->once()
            ->andThrow($exception);

        // Act & Assert
        expect(fn () => $this->service->deleteFile($key))
            ->toThrow(StorageException::class, 'Failed to delete file');
    });

    test('throws file not found exception on copy', function () {
        // Arrange
        $sourceKey = 'documents/nonexistent.pdf';
        $destinationKey = 'documents/copy.pdf';

        $exception = Mockery::mock(S3Exception::class);
        $exception->shouldReceive('getAwsErrorCode')->andReturn('NoSuchKey');
        $exception->shouldReceive('getMessage')->andReturn('The specified key does not exist.');

        $this->s3Client->shouldReceive('copyObject')
            ->once()
            ->andThrow($exception);

        // Act & Assert
        expect(fn () => $this->service->copyFile($sourceKey, $destinationKey))
            ->toThrow(StorageException::class, 'File not found');
    });

    test('throws access denied exception on copy', function () {
        // Arrange
        $sourceKey = 'documents/source.pdf';
        $destinationKey = 'documents/copy.pdf';

        $exception = Mockery::mock(S3Exception::class);
        $exception->shouldReceive('getAwsErrorCode')->andReturn('AccessDenied');
        $exception->shouldReceive('getMessage')->andReturn('Access Denied');

        $this->s3Client->shouldReceive('copyObject')
            ->once()
            ->andThrow($exception);

        // Act & Assert
        expect(fn () => $this->service->copyFile($sourceKey, $destinationKey))
            ->toThrow(StorageException::class, 'Access denied for copy operation');
    });

    test('throws copy failed exception on AWS error', function () {
        // Arrange
        $sourceKey = 'documents/source.pdf';
        $destinationKey = 'documents/copy.pdf';

        $exception = new AwsException('Service unavailable', Mockery::mock('Aws\\Command'));

        $this->s3Client->shouldReceive('copyObject')
            ->once()
            ->andThrow($exception);

        // Act & Assert
        expect(fn () => $this->service->copyFile($sourceKey, $destinationKey))
            ->toThrow(StorageException::class, 'Failed to copy file');
    });

    test('throws access denied exception on metadata retrieval', function () {
        // Arrange
        $key = 'documents/test.pdf';

        $exception = Mockery::mock(S3Exception::class);
        $exception->shouldReceive('getAwsErrorCode')->andReturn('AccessDenied');
        $exception->shouldReceive('getMessage')->andReturn('Access Denied');

        $this->s3Client->shouldReceive('headObject')
            ->once()
            ->andThrow($exception);

        // Act & Assert
        expect(fn () => $this->service->getFileMetadata($key))
            ->toThrow(StorageException::class, 'Access denied for metadata operation');
    });

    test('throws metadata failed exception on S3 error', function () {
        // Arrange
        $key = 'documents/test.pdf';

        $exception = new S3Exception('Internal Error', Mockery::mock('Aws\\Command'));

        $this->s3Client->shouldReceive('headObject')
            ->once()
            ->andThrow($exception);

        // Act & Assert
        expect(fn () => $this->service->getFileMetadata($key))
            ->toThrow(StorageException::class, 'Failed to get metadata');
    });
});
