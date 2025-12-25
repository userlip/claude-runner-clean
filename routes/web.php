<?php

use App\Http\Controllers\GitHubAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth'])->prefix('admin/github')->group(function () {
    Route::get('/redirect', [GitHubAuthController::class, 'redirect'])->name('github.redirect');
    Route::get('/callback', [GitHubAuthController::class, 'callback'])->name('github.callback');
    Route::delete('/disconnect', [GitHubAuthController::class, 'disconnect'])->name('github.disconnect');
});
