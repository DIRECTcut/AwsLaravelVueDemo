<?php

namespace App\Contracts\Aws;

interface NotificationServiceInterface
{
    public function publish(string $topicArn, string $message, ?string $subject = null, array $messageAttributes = []): string;

    public function subscribe(string $topicArn, string $protocol, string $endpoint): string;

    public function unsubscribe(string $subscriptionArn): bool;

    public function createTopic(string $topicName): string;

    public function deleteTopic(string $topicArn): bool;

    public function listSubscriptions(?string $topicArn = null): array;

    public function confirmSubscription(string $topicArn, string $token): string;
}
