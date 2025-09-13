<?php

namespace App\Contracts\Repositories;

use App\Models\Document;
use App\Models\User;
use App\ProcessingStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface DocumentRepositoryInterface
{
    public function create(array $data): Document;

    public function findById(int $id): ?Document;

    public function findByIdForUser(int $id, User $user): ?Document;

    public function findByS3Key(string $s3Key): ?Document;

    public function updateProcessingStatus(int $documentId, ProcessingStatus $status): bool;

    public function getUserDocuments(User $user, int $perPage = 15): LengthAwarePaginator;

    public function getDocumentsByStatus(ProcessingStatus $status): Collection;

    public function searchDocuments(string $query, User $user = null, int $perPage = 15): LengthAwarePaginator;

    public function getDocumentsByTags(array $tags, User $user = null): Collection;

    public function delete(int $id): bool;

    public function getRecentDocuments(User $user, int $limit = 10): Collection;

    public function getDocumentStats(User $user = null): array;
}