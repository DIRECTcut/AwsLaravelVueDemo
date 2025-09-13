<?php

namespace App\Contracts\Processing;

use App\Models\Document;

interface DocumentProcessorInterface
{
    public function canProcess(Document $document): bool;

    public function process(Document $document): array;

    public function getSupportedMimeTypes(): array;

    public function getPriority(): int;
}