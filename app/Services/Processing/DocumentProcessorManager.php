<?php

namespace App\Services\Processing;

use App\Contracts\Processing\DocumentProcessorInterface;
use App\Models\Document;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DocumentProcessorManager
{
    /**
     * @var Collection<DocumentProcessorInterface>
     */
    private Collection $processors;

    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->processors = collect();
        $this->logger = $logger ?? new NullLogger;
    }

    /**
     * Register a document processor
     */
    public function register(DocumentProcessorInterface $processor): void
    {
        $this->processors->push($processor);

        // Sort by priority (highest first)
        $this->processors = $this->processors->sortByDesc(function ($processor) {
            return $processor->getPriority();
        });

        $this->logger->debug('Registered document processor', [
            'processor' => get_class($processor),
            'priority' => $processor->getPriority(),
            'supported_types' => $processor->getSupportedMimeTypes(),
        ]);
    }

    /**
     * Get all registered processors
     */
    public function getProcessors(): Collection
    {
        return $this->processors;
    }

    /**
     * Find the best processor for a document
     */
    public function findProcessor(Document $document): ?DocumentProcessorInterface
    {
        foreach ($this->processors as $processor) {
            if ($processor->canProcess($document)) {
                $this->logger->info('Found processor for document', [
                    'document_id' => $document->id,
                    'processor' => get_class($processor),
                    'mime_type' => $document->mime_type,
                ]);

                return $processor;
            }
        }

        $this->logger->warning('No processor found for document', [
            'document_id' => $document->id,
            'mime_type' => $document->mime_type,
            'available_processors' => $this->processors->count(),
        ]);

        return null;
    }

    /**
     * Process a document using the appropriate processor
     */
    public function processDocument(Document $document): array
    {
        $processor = $this->findProcessor($document);

        if (! $processor) {
            throw new \RuntimeException(
                "No processor available for document type: {$document->mime_type}"
            );
        }

        $this->logger->info('Processing document with strategy', [
            'document_id' => $document->id,
            'processor' => get_class($processor),
            'strategy' => 'document_processor_pattern',
        ]);

        return $processor->process($document);
    }

    /**
     * Get supported mime types from all processors
     */
    public function getSupportedMimeTypes(): array
    {
        return $this->processors
            ->flatMap(fn ($processor) => $processor->getSupportedMimeTypes())
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Check if a mime type is supported
     */
    public function isSupported(string $mimeType): bool
    {
        return in_array($mimeType, $this->getSupportedMimeTypes());
    }

    /**
     * Get processor statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_processors' => $this->processors->count(),
            'supported_mime_types' => count($this->getSupportedMimeTypes()),
            'processors' => $this->processors->map(function ($processor) {
                return [
                    'class' => get_class($processor),
                    'priority' => $processor->getPriority(),
                    'supported_types' => $processor->getSupportedMimeTypes(),
                ];
            })->toArray(),
        ];
    }
}
