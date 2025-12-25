<?php

use App\Http\Controllers\Api\TaskMessagesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/tasks/{task}/messages', [TaskMessagesController::class, 'index'])
        ->name('api.tasks.messages');
});
