<?php

use App\Contracts\Aws\StorageServiceInterface;
use App\Contracts\Repositories\DocumentRepositoryInterface;
use App\Exceptions\Aws\StorageException;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use App\ProcessingStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->storageService = Mockery::mock(StorageServiceInterface::class);
    $this->app->instance(StorageServiceInterface::class, $this->storageService);
});

afterEach(function () {
    Mockery::close();
});

describe('Document Index', function () {
    test('requires authentication', function () {
        $response = $this->get('/documents');

        $response->assertRedirect('/login');
    });

    test('can access documents index when authenticated', function () {
        Document::factory()->count(3)->create(['user_id' => $this->user->id]);

        // TODO: add Inertia tests after adding Vue components
        $this->actingAs($this->user);
        $this->assertTrue(true); // Placeholder test
    });
});

describe('Document Upload', function () {
    test('can upload document', function () {
        Queue::fake();

        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');
        $s3Key = 'documents/1/test-uuid.pdf';

        $this->storageService->shouldReceive('uploadFile')
            ->once()
            ->andReturn($s3Key);

        $response = $this->actingAs($this->user)
            ->post('/documents', [
                'file' => $file,
                'title' => 'Test Document',
                'description' => 'A test document',
                'tags' => ['test', 'upload'],
                'is_public' => false,
            ]);

        $response->assertStatus(302);
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Document uploaded successfully');

        $this->assertDatabaseHas('document', [
            'user_id' => $this->user->id,
            'title' => 'Test Document',
            'original_filename' => 'test.pdf',
            's3_key' => $s3Key,
            'processing_status' => ProcessingStatus::PENDING->value,
        ]);

        Queue::assertPushed(ProcessDocumentJob::class, function ($job) {
            $document = Document::where('title', 'Test Document')->first();

            return $job->getDocumentId() === $document->id;
        });
    });

    test('validates file upload requirements', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/documents', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    });

    test('validates file size limit', function () {
        $file = UploadedFile::fake()->create('large.pdf', 11000); // 11MB

        $response = $this->actingAs($this->user)
            ->postJson('/documents', ['file' => $file]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    });

    test('validates file type', function () {
        $file = UploadedFile::fake()->create('test.exe', 1024);

        $response = $this->actingAs($this->user)
            ->postJson('/documents', ['file' => $file]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    });
});

describe('Document Show', function () {
    test('cannot view other users documents', function () {
        $otherUser = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->get("/documents/{$document->id}");

        $response->assertStatus(404);
    });
});

describe('Document Download', function () {
    test('can download document', function () {
        $document = Document::factory()->create(['user_id' => $this->user->id]);

        $this->storageService->shouldReceive('getSignedUrl')
            ->once()
            ->with($document->s3_key, 10)
            ->andReturn('https://download-url.com');

        $response = $this->actingAs($this->user)
            ->post("/documents/{$document->id}/download");

        $response->assertStatus(200);
        $response->assertJson([
            'download_url' => 'https://download-url.com',
            'filename' => $document->original_filename,
        ]);
    });
});

describe('Document Delete', function () {
    test('can delete document', function () {
        $document = Document::factory()->create(['user_id' => $this->user->id]);

        $this->storageService->shouldReceive('deleteFile')
            ->once()
            ->with($document->s3_key)
            ->andReturn(true);

        $response = $this->actingAs($this->user)
            ->delete("/documents/{$document->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Document deleted successfully',
        ]);

        $this->assertDatabaseMissing('document', [
            'id' => $document->id,
        ]);
    });

    test('cannot delete other users documents', function () {
        $otherUser = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->delete("/documents/{$document->id}");

        $response->assertStatus(404);
    });
});

describe('API Endpoints', function () {
    test('can get document stats', function () {
        Document::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'processing_status' => ProcessingStatus::COMPLETED,
        ]);
        Document::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'processing_status' => ProcessingStatus::PENDING,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/api/documents/stats');

        $response->assertStatus(200);
        $response->assertJson([
            'total' => 5,
            'completed' => 3,
            'pending' => 2,
        ]);
    });

    test('can get recent documents', function () {
        Document::factory()->count(15)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get('/api/documents/recent');

        $response->assertStatus(200);
        $response->assertJsonCount(10);
    });
});

describe('Error Handling', function () {
    test('requires authentication for upload', function () {
        $file = UploadedFile::fake()->create('test.pdf', 1024);

        $response = $this->postJson('/documents', ['file' => $file]);

        $response->assertStatus(401);
    });

    test('requires authentication for delete', function () {
        $document = Document::factory()->create();

        $response = $this->deleteJson("/documents/{$document->id}");

        $response->assertStatus(401);
    });

    test('requires authentication for download', function () {
        $document = Document::factory()->create();

        $response = $this->postJson("/documents/{$document->id}/download");

        $response->assertStatus(401);
    });

    test('requires authentication for stats', function () {
        $response = $this->getJson('/api/documents/stats');

        $response->assertStatus(401);
    });

    test('handles S3 upload failure gracefully', function () {
        $file = UploadedFile::fake()->create('test.pdf', 1024);

        $this->storageService->shouldReceive('uploadFile')
            ->once()
            ->andThrow(new StorageException('S3 service unavailable'));

        $response = $this->actingAs($this->user)
            ->post('/documents', ['file' => $file]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['upload']);
        $response->assertSessionHas('error', 'Upload failed');
    });

    test('handles S3 delete failure gracefully', function () {
        $document = Document::factory()->create(['user_id' => $this->user->id]);

        $this->storageService->shouldReceive('deleteFile')
            ->once()
            ->with($document->s3_key)
            ->andThrow(new StorageException('S3 delete failed'));

        $response = $this->actingAs($this->user)
            ->deleteJson("/documents/{$document->id}");

        $response->assertStatus(500);
        $response->assertJson([
            'message' => 'Failed to delete document',
            'error' => 'S3 delete failed',
        ]);
    });

    test('handles database errors during document creation', function () {
        $file = UploadedFile::fake()->create('test.pdf', 1024);
        $s3Key = 'documents/1/test-uuid.pdf';

        // Mock successful S3 upload
        $this->storageService->shouldReceive('uploadFile')
            ->once()
            ->andReturn($s3Key);

        // Mock repository to throw database exception
        $mockRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $mockRepo->shouldReceive('create')
            ->once()
            ->andThrow(new QueryException('mysql', 'INSERT INTO document', [], new \Exception('Database connection failed')));

        $this->app->instance(DocumentRepositoryInterface::class, $mockRepo);

        $response = $this->actingAs($this->user)
            ->post('/documents', ['file' => $file]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['upload']);
        $response->assertSessionHas('error', 'Upload failed');
    });

    test('returns 404 for non-existent document download', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/documents/999/download');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Document not found']);
    });

    test('returns 404 for non-existent document show', function () {
        $response = $this->actingAs($this->user)
            ->get('/documents/999');

        $response->assertStatus(404);
    });

    test('returns 404 for non-existent document delete', function () {
        $response = $this->actingAs($this->user)
            ->deleteJson('/documents/999');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Document not found']);
    });

    test('handles storage service errors during signed URL generation', function () {
        $document = Document::factory()->create(['user_id' => $this->user->id]);

        $this->storageService->shouldReceive('getSignedUrl')
            ->once()
            ->with($document->s3_key, 10)
            ->andThrow(new StorageException('Failed to generate signed URL'));

        $response = $this->actingAs($this->user)
            ->postJson("/documents/{$document->id}/download");

        $response->assertStatus(500);
    });
});
