<?php

namespace Tests\Unit\Events;

use App\Events\DocumentProcessingStatusUpdated;
use App\Models\Document;
use App\Models\User;
use App\ProcessingStatus;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentProcessingStatusUpdatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_broadcasts_on_correct_channels(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create();

        $event = new DocumentProcessingStatusUpdated(
            $document,
            ProcessingStatus::PROCESSING,
            'Test message'
        );

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);
        $this->assertEquals("private-user.{$user->id}", $channels[0]->name);
        $this->assertEquals("private-document.{$document->id}", $channels[1]->name);
    }

    public function test_event_includes_correct_broadcast_data(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'title' => 'Test Document',
        ]);

        $event = new DocumentProcessingStatusUpdated(
            $document,
            ProcessingStatus::COMPLETED,
            'Processing complete',
            ['confidence' => 0.95]
        );

        $data = $event->broadcastWith();

        $this->assertEquals($document->id, $data['document_id']);
        $this->assertEquals('Test Document', $data['document_title']);
        $this->assertEquals('completed', $data['status']);
        $this->assertEquals('Completed', $data['status_label']);
        $this->assertEquals('Processing complete', $data['message']);
        $this->assertEquals(['confidence' => 0.95], $data['metadata']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('progress', $data);
    }

    public function test_event_uses_correct_broadcast_name(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create();

        $event = new DocumentProcessingStatusUpdated(
            $document,
            ProcessingStatus::PROCESSING
        );

        $this->assertEquals('document.status.updated', $event->broadcastAs());
    }

    public function test_progress_calculation_with_no_jobs(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create();

        $event = new DocumentProcessingStatusUpdated(
            $document,
            ProcessingStatus::PROCESSING
        );

        $data = $event->broadcastWith();
        $this->assertEquals(50, $data['progress']);

        $event = new DocumentProcessingStatusUpdated(
            $document,
            ProcessingStatus::COMPLETED
        );

        $data = $event->broadcastWith();
        $this->assertEquals(100, $data['progress']);
    }
}
