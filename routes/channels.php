<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    \Log::info('Broadcasting auth for App.Models.User channel', ['user_id' => $user->id, 'channel_id' => $id]);

    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    \Log::info('Broadcasting auth for user channel', ['user_id' => $user->id, 'channel_user_id' => $userId]);

    return (int) $user->id === (int) $userId;
});

Broadcast::channel('document.{documentId}', function ($user, $documentId) {
    \Log::info('Broadcasting auth for document channel', ['user_id' => $user->id, 'document_id' => $documentId]);
    // Check if user owns the document
    $hasAccess = $user->documents()->where('id', $documentId)->exists();
    \Log::info('Document channel access result', ['has_access' => $hasAccess]);

    return $hasAccess;
});
