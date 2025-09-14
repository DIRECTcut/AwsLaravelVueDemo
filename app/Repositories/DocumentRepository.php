<?php

namespace App\Repositories;

use App\Contracts\Repositories\DocumentRepositoryInterface;
use App\Models\Document;
use App\Models\User;
use App\ProcessingStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DocumentRepository implements DocumentRepositoryInterface
{
    public function create(array $data): Document
    {
        return Document::create($data);
    }

    public function findById(int $id): ?Document
    {
        return Document::find($id);
    }

    public function findByIdForUser(int $id, User $user): ?Document
    {
        return Document::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function findByS3Key(string $s3Key): ?Document
    {
        return Document::where('s3_key', $s3Key)->first();
    }

    public function updateProcessingStatus(int $documentId, ProcessingStatus $status): bool
    {
        return Document::where('id', $documentId)
            ->update(['processing_status' => $status]);
    }

    public function getUserDocuments(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Document::where('user_id', $user->id)
            ->orderBy('uploaded_at', 'desc')
            ->paginate($perPage);
    }

    public function getDocumentsByStatus(ProcessingStatus $status): Collection
    {
        return Document::where('processing_status', $status)
            ->orderBy('uploaded_at', 'desc')
            ->get();
    }

    public function searchDocuments(string $query, ?User $user = null, int $perPage = 15): LengthAwarePaginator
    {
        $queryBuilder = Document::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('original_filename', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%");
        });

        if ($user) {
            $queryBuilder->where('user_id', $user->id);
        }

        return $queryBuilder
            ->orderBy('uploaded_at', 'desc')
            ->paginate($perPage);
    }

    public function getDocumentsByTags(array $tags, ?User $user = null): Collection
    {
        $queryBuilder = Document::whereJsonContains('tags', $tags);

        if ($user) {
            $queryBuilder->where('user_id', $user->id);
        }

        return $queryBuilder
            ->orderBy('uploaded_at', 'desc')
            ->get();
    }

    public function delete(int $id): bool
    {
        return Document::destroy($id) > 0;
    }

    public function getRecentDocuments(User $user, int $limit = 10): Collection
    {
        return Document::where('user_id', $user->id)
            ->orderBy('uploaded_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getDocumentStats(?User $user = null): array
    {
        $baseQuery = Document::query();

        if ($user) {
            $baseQuery->where('user_id', $user->id);
        }

        $total = (clone $baseQuery)->count();
        $completed = (clone $baseQuery)->where('processing_status', ProcessingStatus::COMPLETED)->count();
        $pending = (clone $baseQuery)->where('processing_status', ProcessingStatus::PENDING)->count();
        $processing = (clone $baseQuery)->where('processing_status', ProcessingStatus::PROCESSING)->count();
        $failed = (clone $baseQuery)->where('processing_status', ProcessingStatus::FAILED)->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'processing' => $processing,
            'failed' => $failed,
        ];
    }
}
