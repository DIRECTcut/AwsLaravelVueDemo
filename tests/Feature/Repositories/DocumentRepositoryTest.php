<?php

use App\Models\Document;
use App\Models\User;
use App\ProcessingStatus;
use App\Repositories\DocumentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = new DocumentRepository();
    $this->user = User::factory()->create();
});

describe('Basic Operations', function () {
    test('can create document', function () {
        $data = [
            'user_id' => $this->user->id,
            'title' => 'Test Document',
            'original_filename' => 'test.pdf',
            'file_extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            's3_key' => 'documents/test.pdf',
            's3_bucket' => 'test-bucket',
            'uploaded_at' => now(),
        ];
        
        $document = $this->repository->create($data);
        
        expect($document)->toBeInstanceOf(Document::class);
        expect($document->title)->toBe('Test Document');
        expect($document->user_id)->toBe($this->user->id);
    });
    
    test('can find document by id', function () {
        $document = Document::factory()->create(['user_id' => $this->user->id]);
        
        $found = $this->repository->findById($document->id);
        
        expect($found)->toBeInstanceOf(Document::class);
        expect($found->id)->toBe($document->id);
    });
    
    test('returns null when document not found', function () {
        $found = $this->repository->findById(999);
        
        expect($found)->toBeNull();
    });
    
    test('can find document by id for user', function () {
        $document = Document::factory()->create(['user_id' => $this->user->id]);
        $otherUser = User::factory()->create();
        
        $found = $this->repository->findByIdForUser($document->id, $this->user);
        $notFound = $this->repository->findByIdForUser($document->id, $otherUser);
        
        expect($found)->toBeInstanceOf(Document::class);
        expect($notFound)->toBeNull();
    });
    
    test('can find document by S3 key', function () {
        $s3Key = 'documents/unique-key.pdf';
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            's3_key' => $s3Key,
        ]);
        
        $found = $this->repository->findByS3Key($s3Key);
        
        expect($found)->toBeInstanceOf(Document::class);
        expect($found->s3_key)->toBe($s3Key);
    });
    
    test('can update processing status', function () {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'processing_status' => ProcessingStatus::PENDING,
        ]);
        
        $result = $this->repository->updateProcessingStatus($document->id, ProcessingStatus::COMPLETED);
        $document->refresh();
        
        expect($result)->toBeTrue();
        expect($document->processing_status)->toBe(ProcessingStatus::COMPLETED);
    });
    
    test('can delete document', function () {
        $document = Document::factory()->create(['user_id' => $this->user->id]);
        
        $result = $this->repository->delete($document->id);
        
        expect($result)->toBeTrue();
        expect(Document::find($document->id))->toBeNull();
    });
});

describe('Queries and Filtering', function () {
    test('can get user documents with pagination', function () {
        Document::factory()->count(15)->create(['user_id' => $this->user->id]);
        Document::factory()->count(5)->create(); // Other user's documents
        
        $paginated = $this->repository->getUserDocuments($this->user, 10);
        
        expect($paginated->total())->toBe(15);
        expect($paginated->perPage())->toBe(10);
        expect($paginated->items())->toHaveCount(10);
    });
    
    test('can get documents by status', function () {
        Document::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'processing_status' => ProcessingStatus::PENDING,
        ]);
        Document::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'processing_status' => ProcessingStatus::COMPLETED,
        ]);
        
        $pendingDocs = $this->repository->getDocumentsByStatus(ProcessingStatus::PENDING);
        
        expect($pendingDocs)->toHaveCount(3);
        expect($pendingDocs->first()->processing_status)->toBe(ProcessingStatus::PENDING);
    });
    
    test('can search documents', function () {
        Document::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Important Contract',
        ]);
        Document::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Random Document',
        ]);
        
        $results = $this->repository->searchDocuments('Contract', $this->user, 10);
        
        expect($results->total())->toBe(1);
        expect($results->first()->title)->toContain('Contract');
    });
    
    test('can get documents by tags', function () {
        Document::factory()->create([
            'user_id' => $this->user->id,
            'tags' => ['urgent', 'contract'],
        ]);
        Document::factory()->create([
            'user_id' => $this->user->id,
            'tags' => ['personal'],
        ]);
        
        $urgentDocs = $this->repository->getDocumentsByTags(['urgent'], $this->user);
        
        expect($urgentDocs)->toHaveCount(1);
        expect($urgentDocs->first()->tags)->toContain('urgent');
    });
    
    test('can get recent documents', function () {
        Document::factory()->count(15)->create(['user_id' => $this->user->id]);
        
        $recent = $this->repository->getRecentDocuments($this->user, 5);
        
        expect($recent)->toHaveCount(5);
    });
    
    test('can get document stats for user', function () {
        Document::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'processing_status' => ProcessingStatus::COMPLETED,
        ]);
        Document::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'processing_status' => ProcessingStatus::PENDING,
        ]);
        
        $stats = $this->repository->getDocumentStats($this->user);
        
        expect($stats)->toHaveKey('total');
        expect($stats)->toHaveKey('completed');
        expect($stats)->toHaveKey('pending');
        expect($stats['total'])->toBe(5);
        expect($stats['completed'])->toBe(3);
        expect($stats['pending'])->toBe(2);
    });
});
