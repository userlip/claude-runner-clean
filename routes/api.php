<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\TaskMessagesController;
use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/tasks/{task}/messages', [TaskMessagesController::class, 'index'])
        ->name('api.tasks.messages');

    // Chat API endpoints
    Route::prefix('chat/{task:uuid}')->name('api.chat.')->group(function () {
        Route::get('/messages', [ChatController::class, 'messages'])->name('messages');
        Route::post('/messages', [ChatController::class, 'sendMessage'])->name('send');
        Route::get('/status', [ChatController::class, 'status'])->name('status');
        Route::get('/queued', [ChatController::class, 'queuedMessages'])->name('queued');
        Route::delete('/queued/{message}', [ChatController::class, 'deleteQueuedMessage'])->name('queued.delete');
        Route::post('/messages/{message}/question-response', [ChatController::class, 'submitQuestionResponse'])->name('question-response');
    });

    // Push notification subscriptions
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])
        ->name('api.push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])
        ->name('api.push.unsubscribe');
});

// Public route for VAPID key
Route::get('/push/vapid-public-key', [PushSubscriptionController::class, 'vapidPublicKey'])
    ->name('api.push.vapid-public-key');

// Telegram webhook - uses secret token in URL for security
Route::post('/telegram/webhook/{secret}', [TelegramWebhookController::class, 'handle'])
    ->name('api.telegram.webhook')
    ->middleware('throttle:60,1');
