<?php

namespace App\Models;

use App\JobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentProcessingJob extends Model
{
    use HasFactory;

    protected $table = 'document_processing_job';

    protected $fillable = [
        'document_id',
        'job_type',
        'status',
        'aws_job_id',
        'job_parameters',
        'result_data',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => JobStatus::class,
        'job_parameters' => 'array',
        'result_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function markAsStarted(): void
    {
        $this->update([
            'status' => JobStatus::PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(array $resultData): void
    {
        $this->update([
            'status' => JobStatus::COMPLETED,
            'result_data' => $resultData,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => JobStatus::FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }
}
