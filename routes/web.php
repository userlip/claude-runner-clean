<?php

use App\Http\Controllers\GitHubAuthController;
use App\Livewire\ScrappApis\Form as ScrappApiForm;
use App\Livewire\ScrappApis\Index as ScrappApiIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/app');
});

Route::middleware(['auth'])->prefix('app')->group(function () {
    Route::get('/', function () {
        return view('app');
    })->name('app.home');

    Route::middleware(['role:admin'])->prefix('scrapp-apis')->group(function () {
        Route::get('/', ScrappApiIndex::class)->name('app.scrapp-apis.index');
        Route::get('/create', ScrappApiForm::class)->name('app.scrapp-apis.create');
        Route::get('/{id}/edit', ScrappApiForm::class)->name('app.scrapp-apis.edit');
    });
});

Route::get('/app-manifest.json', function () {
    return response()->json([
        'name' => config('app.name'),
        'short_name' => config('app.name'),
        'start_url' => '/app',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#000000',
        'orientation' => 'portrait',
        'icons' => [
            [
                'src' => '/images/icons/icon-192x192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ],
            [
                'src' => '/images/icons/icon-512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ],
        ],
    ])->header('Content-Type', 'application/manifest+json');
})->name('app.manifest');

Route::get('/ide-auth-check', function () {
    return auth()->check() ? response('OK') : response('Unauthorized', 401);
})->middleware('web')->name('ide.auth-check');

Route::middleware(['auth'])->prefix('admin/github')->group(function () {
    Route::get('/redirect', [GitHubAuthController::class, 'redirect'])->name('github.redirect');
    Route::get('/callback', [GitHubAuthController::class, 'callback'])->name('github.callback');
    Route::delete('/disconnect', [GitHubAuthController::class, 'disconnect'])->name('github.disconnect');
});
