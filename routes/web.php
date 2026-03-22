<?php

use App\Http\Controllers\GitHubAuthController;
use App\Livewire\MajorUpgradeRuns\Index as MajorUpgradeRunsIndex;
use App\Livewire\Personas\Form as PersonaForm;
use App\Livewire\Personas\Index as PersonasIndex;
use App\Livewire\Playbooks\Form as PlaybookForm;
use App\Livewire\Playbooks\Index as PlaybooksIndex;
use App\Livewire\PromotionDirectories\Form as PromotionDirectoryForm;
use App\Livewire\PromotionDirectories\Index as PromotionDirectoriesIndex;
use App\Livewire\Proposals\Form as ProposalForm;
use App\Livewire\Proposals\Index as ProposalsIndex;
use App\Livewire\Repositories\Form as RepositoryForm;
use App\Livewire\Repositories\Index as RepositoriesIndex;
use App\Livewire\ResearchReports\Index as ResearchReportsIndex;
use App\Livewire\ResearchReports\Show as ResearchReportShow;
use App\Livewire\Schedules\Form as ScheduleForm;
use App\Livewire\Schedules\Index as SchedulesIndex;
use App\Livewire\ScrappApis\Form as ScrappApiForm;
use App\Livewire\ScrappApis\Index as ScrappApiIndex;
use App\Livewire\SecurityRuns\Index as SecurityRunsIndex;
use App\Livewire\Sites\Form as SiteForm;
use App\Livewire\Sites\Index as SitesIndex;
use App\Livewire\Snippets\Form as SnippetForm;
use App\Livewire\Snippets\Index as SnippetsIndex;
use App\Livewire\Tasks\Form as TaskForm;
use App\Livewire\Tasks\Index as TasksIndex;
use App\Livewire\Users\Form as UserForm;
use App\Livewire\Users\Index as UsersIndex;
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

    Route::middleware(['role:admin'])->prefix('proposals')->group(function () {
        Route::get('/', ProposalsIndex::class)->name('app.proposals.index');
        Route::get('/create', ProposalForm::class)->name('app.proposals.create');
        Route::get('/{uuid}/edit', ProposalForm::class)->name('app.proposals.edit');
    });

    Route::middleware(['role:admin'])->prefix('promotion-directories')->group(function () {
        Route::get('/', PromotionDirectoriesIndex::class)->name('app.promotion-directories.index');
        Route::get('/create', PromotionDirectoryForm::class)->name('app.promotion-directories.create');
        Route::get('/{uuid}/edit', PromotionDirectoryForm::class)->name('app.promotion-directories.edit');
    });

    Route::middleware(['role:admin'])->prefix('personas')->group(function () {
        Route::get('/', PersonasIndex::class)->name('app.personas.index');
        Route::get('/create', PersonaForm::class)->name('app.personas.create');
        Route::get('/{slug}/edit', PersonaForm::class)->name('app.personas.edit');
    });

    Route::middleware(['role:admin'])->prefix('playbooks')->group(function () {
        Route::get('/', PlaybooksIndex::class)->name('app.playbooks.index');
        Route::get('/create', PlaybookForm::class)->name('app.playbooks.create');
        Route::get('/{id}/edit', PlaybookForm::class)->name('app.playbooks.edit');
    });

    Route::middleware(['role:admin'])->prefix('snippets')->group(function () {
        Route::get('/', SnippetsIndex::class)->name('app.snippets.index');
        Route::get('/create', SnippetForm::class)->name('app.snippets.create');
        Route::get('/{id}/edit', SnippetForm::class)->name('app.snippets.edit');
    });

    Route::middleware(['role:admin'])->prefix('sites')->group(function () {
        Route::get('/', SitesIndex::class)->name('app.sites.index');
        Route::get('/create', SiteForm::class)->name('app.sites.create');
        Route::get('/{id}/edit', SiteForm::class)->name('app.sites.edit');
    });

    Route::middleware(['role:admin'])->prefix('repositories')->group(function () {
        Route::get('/', RepositoriesIndex::class)->name('app.repositories.index');
        Route::get('/create', RepositoryForm::class)->name('app.repositories.create');
        Route::get('/{id}/edit', RepositoryForm::class)->name('app.repositories.edit');
    });

    Route::middleware(['role:admin'])->prefix('tasks')->group(function () {
        Route::get('/', TasksIndex::class)->name('app.tasks.index');
        Route::get('/create', TaskForm::class)->name('app.tasks.create');
        Route::get('/{id}/edit', TaskForm::class)->name('app.tasks.edit');
    });

    Route::middleware(['role:admin'])->prefix('schedules')->group(function () {
        Route::get('/', SchedulesIndex::class)->name('app.schedules.index');
        Route::get('/create', ScheduleForm::class)->name('app.schedules.create');
        Route::get('/{id}/edit', ScheduleForm::class)->name('app.schedules.edit');
    });

    Route::middleware(['role:admin'])->prefix('users')->group(function () {
        Route::get('/', UsersIndex::class)->name('app.users.index');
        Route::get('/create', UserForm::class)->name('app.users.create');
        Route::get('/{id}/edit', UserForm::class)->name('app.users.edit');
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
