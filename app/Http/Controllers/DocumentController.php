<?php

namespace App\Http\Controllers;

use App\Contracts\Aws\StorageServiceInterface;
use App\Contracts\Repositories\DocumentRepositoryInterface;
use App\Http\Requests\StoreDocumentRequest;
use App\Jobs\ProcessDocumentJob;
use App\ProcessingStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentRepositoryInterface $documentRepository,
        private StorageServiceInterface $storageService
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $query = $request->get('search');

        if ($query) {
            $documents = $this->documentRepository->searchDocuments($query, $user, 15);
        } else {
            $documents = $this->documentRepository->getUserDocuments($user, 15);
        }

        $stats = $this->documentRepository->getDocumentStats($user);

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'stats' => $stats,
            'search' => $query,
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        try {
            $user = Auth::user();
            $file = $request->file('file');

            // Upload file to S3
            $s3Key = $this->storageService->uploadFile(
                $file,
                "documents/{$user->id}",
                [
                    'user_id' => (string) $user->id,
                    'uploaded_at' => now()->toISOString(),
                ]
            );

            // Create document record
            $document = $this->documentRepository->create([
                'user_id' => $user->id,
                'title' => $request->input('title') ?: $file->getClientOriginalName(),
                'original_filename' => $file->getClientOriginalName(),
                'file_extension' => $file->getClientOriginalExtension(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                's3_key' => $s3Key,
                's3_bucket' => config('filesystems.disks.s3.bucket'),
                'processing_status' => ProcessingStatus::PENDING,
                'description' => $request->input('description'),
                'tags' => $request->input('tags', []),
                'is_public' => $request->boolean('is_public', false),
                'uploaded_at' => now(),
            ]);

            ProcessDocumentJob::dispatch($document->id);

            return back()->with('success', 'Document uploaded successfully')
                ->with('document', $document->load('user'));

        } catch (\Exception $e) {
            return back()->withErrors([
                'upload' => 'Failed to upload document: '.$e->getMessage(),
            ])->with('error', 'Upload failed');
        }
    }

    public function show(int $id): Response
    {
        $user = Auth::user();
        $document = $this->documentRepository->findByIdForUser($id, $user);

        if (! $document) {
            abort(404, 'Document not found');
        }

        $signedUrl = $this->storageService->getSignedUrl($document->s3_key, 60);

        return Inertia::render('Documents/Show', [
            'document' => $document->load(['processingJobs', 'analysisResults']),
            'downloadUrl' => $signedUrl,
        ]);
    }

    public function download(int $id): JsonResponse
    {
        $user = Auth::user();
        $document = $this->documentRepository->findByIdForUser($id, $user);

        if (! $document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        try {
            $signedUrl = $this->storageService->getSignedUrl($document->s3_key, 10);

            return response()->json([
                'download_url' => $signedUrl,
                'filename' => $document->original_filename,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate download URL',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $document = $this->documentRepository->findByIdForUser($id, $user);

        if (! $document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        try {
            // Delete from S3
            $this->storageService->deleteFile($document->s3_key);

            // Delete from database
            $this->documentRepository->delete($document->id);

            return response()->json([
                'message' => 'Document deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function stats(): JsonResponse
    {
        $user = Auth::user();
        $stats = $this->documentRepository->getDocumentStats($user);

        return response()->json($stats);
    }

    public function recent(): JsonResponse
    {
        $user = Auth::user();
        $documents = $this->documentRepository->getRecentDocuments($user, 10);

        return response()->json($documents);
    }
}
