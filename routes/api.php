<?php

use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\TaskMessagesController;
use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/tasks/{task}/messages', [TaskMessagesController::class, 'index'])
        ->name('api.tasks.messages');

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
