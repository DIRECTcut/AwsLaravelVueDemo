<?php

namespace App\Events;

use App\Models\Document;
use App\ProcessingStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentProcessingStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Document $document,
        public readonly ProcessingStatus $status,
        public readonly ?string $message = null,
        public readonly ?array $metadata = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->document->user_id}"),
            new PrivateChannel("document.{$this->document->id}"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'message' => $this->message,
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
            'progress' => $this->calculateProgress(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'document.status.updated';
    }

    /**
     * Calculate processing progress percentage.
     */
    private function calculateProgress(): int
    {
        $totalJobs = $this->document->processingJobs()->count();

        if ($totalJobs === 0) {
            return match ($this->status) {
                ProcessingStatus::PENDING => 0,
                ProcessingStatus::PROCESSING => 50,
                ProcessingStatus::COMPLETED => 100,
                ProcessingStatus::FAILED => 0,
            };
        }

        $completedJobs = $this->document->processingJobs()
            ->whereIn('status', [\App\JobStatus::COMPLETED, \App\JobStatus::FAILED])
            ->count();

        return (int) round(($completedJobs / $totalJobs) * 100);
    }
}
