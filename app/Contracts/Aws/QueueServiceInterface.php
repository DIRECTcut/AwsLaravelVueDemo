<?php

namespace App\Contracts\Aws;

interface QueueServiceInterface
{
    public function sendMessage(string $queueUrl, array $messageBody, array $messageAttributes = []): string;

    public function receiveMessages(string $queueUrl, int $maxMessages = 10, int $waitTimeSeconds = 20): array;

    public function deleteMessage(string $queueUrl, string $receiptHandle): bool;

    public function changeMessageVisibility(string $queueUrl, string $receiptHandle, int $visibilityTimeoutSeconds): bool;

    public function getQueueAttributes(string $queueUrl): array;

    public function purgeQueue(string $queueUrl): bool;
}
