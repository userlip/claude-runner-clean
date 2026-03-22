<?php

use App\Http\Controllers\GitHubAuthController;
use App\Livewire\MajorUpgradeRuns\Index as MajorUpgradeRunsIndex;
use App\Livewire\ResearchReports\Index as ResearchReportsIndex;
use App\Livewire\ResearchReports\Show as ResearchReportShow;
use App\Livewire\ScrappApis\Form as ScrappApiForm;
use App\Livewire\ScrappApis\Index as ScrappApiIndex;
use App\Livewire\SecurityRuns\Index as SecurityRunsIndex;
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

    Route::middleware(['role:admin'])->prefix('research-reports')->group(function () {
        Route::get('/', ResearchReportsIndex::class)->name('app.research-reports.index');
        Route::get('/{report:uuid}', ResearchReportShow::class)->name('app.research-reports.show');
    });

    Route::middleware(['role:admin'])->prefix('major-upgrade-runs')->group(function () {
        Route::get('/', MajorUpgradeRunsIndex::class)->name('app.major-upgrade-runs.index');
    });

    Route::middleware(['role:admin'])->prefix('security-runs')->group(function () {
        Route::get('/', SecurityRunsIndex::class)->name('app.security-runs.index');
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
