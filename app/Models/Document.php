<?php

namespace App\Models;

use App\DocumentType;
use App\ProcessingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $table = 'document';

    protected $fillable = [
        'user_id',
        'title',
        'original_filename',
        'file_extension',
        'mime_type',
        'file_size',
        's3_key',
        's3_bucket',
        'processing_status',
        'metadata',
        'description',
        'tags',
        'is_public',
        'uploaded_at',
    ];

    protected $casts = [
        'processing_status' => ProcessingStatus::class,
        'metadata' => 'array',
        'tags' => 'array',
        'is_public' => 'boolean',
        'uploaded_at' => 'datetime',
        'file_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(DocumentProcessingJob::class);
    }

    public function analysisResults(): HasMany
    {
        return $this->hasMany(DocumentAnalysisResult::class);
    }

    public function getDocumentType(): ?DocumentType
    {
        return DocumentType::fromMimeType($this->mime_type);
    }

    public function getS3Url(): string
    {
        return "https://{$this->s3_bucket}.s3.amazonaws.com/{$this->s3_key}";
    }

    public function getHumanReadableSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
