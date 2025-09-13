<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAnalysisResult extends Model
{
    use HasFactory;

    protected $table = 'document_analysis_result';

    protected $fillable = [
        'document_id',
        'analysis_type',
        'raw_results',
        'processed_data',
        'confidence_score',
        'metadata',
    ];

    protected $casts = [
        'raw_results' => 'array',
        'processed_data' => 'array',
        'confidence_score' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isTextractResult(): bool
    {
        return str_starts_with($this->analysis_type, 'textract_');
    }

    public function isComprehendResult(): bool
    {
        return str_starts_with($this->analysis_type, 'comprehend_');
    }

    public function hasHighConfidence(): bool
    {
        return $this->confidence_score !== null && $this->confidence_score >= 0.8;
    }
}
