<?php

use App\Http\Controllers\GitHubAuthController;
use App\Livewire\AiProviders\Index as AiProvidersIndex;
use App\Livewire\Analytics\Index as AnalyticsIndex;
use App\Livewire\Home\Index as HomeIndex;
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
use App\Livewire\SecurityRuns\Index as SecurityRunsIndex;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Livewire\Sites\Form as SiteForm;
use App\Livewire\Sites\Index as SitesIndex;
use App\Livewire\Snippets\Form as SnippetForm;
use App\Livewire\Snippets\Index as SnippetsIndex;
use App\Livewire\Tasks\Form as TaskForm;
use App\Livewire\Tasks\Index as TasksIndex;
use App\Livewire\Tasks\Show as TaskShow;
use App\Livewire\Users\Form as UserForm;
use App\Livewire\Users\Index as UsersIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/workbench');
});

Route::middleware(['auth'])->prefix('workbench')->group(function () {
    Route::get('/', HomeIndex::class)->name('workbench.home');

    Route::middleware(['role:admin'])->prefix('research-reports')->group(function () {
        Route::get('/', ResearchReportsIndex::class)->name('workbench.research-reports.index');
        Route::get('/{report:uuid}', ResearchReportShow::class)->name('workbench.research-reports.show');
    });

    Route::middleware(['role:admin'])->prefix('major-upgrade-runs')->group(function () {
        Route::get('/', MajorUpgradeRunsIndex::class)->name('workbench.major-upgrade-runs.index');
    });

    Route::middleware(['role:admin'])->prefix('security-runs')->group(function () {
        Route::get('/', SecurityRunsIndex::class)->name('workbench.security-runs.index');
    });

    Route::middleware(['role:admin'])->prefix('proposals')->group(function () {
        Route::get('/', ProposalsIndex::class)->name('workbench.proposals.index');
        Route::get('/create', ProposalForm::class)->name('workbench.proposals.create');
        Route::get('/{uuid}/edit', ProposalForm::class)->name('workbench.proposals.edit');
    });

    Route::middleware(['role:admin'])->prefix('promotion-directories')->group(function () {
        Route::get('/', PromotionDirectoriesIndex::class)->name('workbench.promotion-directories.index');
        Route::get('/create', PromotionDirectoryForm::class)->name('workbench.promotion-directories.create');
        Route::get('/{uuid}/edit', PromotionDirectoryForm::class)->name('workbench.promotion-directories.edit');
    });

    Route::middleware(['role:admin'])->prefix('personas')->group(function () {
        Route::get('/', PersonasIndex::class)->name('workbench.personas.index');
        Route::get('/create', PersonaForm::class)->name('workbench.personas.create');
        Route::get('/{slug}/edit', PersonaForm::class)->name('workbench.personas.edit');
    });

    Route::middleware(['role:admin'])->prefix('playbooks')->group(function () {
        Route::get('/', PlaybooksIndex::class)->name('workbench.playbooks.index');
        Route::get('/create', PlaybookForm::class)->name('workbench.playbooks.create');
        Route::get('/{id}/edit', PlaybookForm::class)->name('workbench.playbooks.edit');
    });

    Route::middleware(['role:admin'])->prefix('snippets')->group(function () {
        Route::get('/', SnippetsIndex::class)->name('workbench.snippets.index');
        Route::get('/create', SnippetForm::class)->name('workbench.snippets.create');
        Route::get('/{id}/edit', SnippetForm::class)->name('workbench.snippets.edit');
    });

    Route::middleware(['role:admin'])->prefix('sites')->group(function () {
        Route::get('/', SitesIndex::class)->name('workbench.sites.index');
        Route::get('/create', SiteForm::class)->name('workbench.sites.create');
        Route::get('/{id}/edit', SiteForm::class)->name('workbench.sites.edit');
    });

    Route::middleware(['role:admin'])->prefix('repositories')->group(function () {
        Route::get('/', RepositoriesIndex::class)->name('workbench.repositories.index');
        Route::get('/create', RepositoryForm::class)->name('workbench.repositories.create');
        Route::get('/{id}/edit', RepositoryForm::class)->name('workbench.repositories.edit');
    });

    Route::middleware(['role:admin'])->prefix('tasks')->group(function () {
        Route::get('/', TasksIndex::class)->name('workbench.tasks.index');
        Route::get('/create', TaskForm::class)->name('workbench.tasks.create');
        Route::get('/{id}/edit', TaskForm::class)->name('workbench.tasks.edit');
    });

    Route::get('/tasks/{uuid}', TaskShow::class)->name('workbench.tasks.show');

    Route::get('/settings', SettingsIndex::class)->name('workbench.settings.index');
    Route::get('/settings/ai-providers', AiProvidersIndex::class)->name('workbench.ai-providers.index');
    Route::get('/analytics', AnalyticsIndex::class)->name('workbench.analytics.index');

    Route::middleware(['role:admin'])->prefix('schedules')->group(function () {
        Route::get('/', SchedulesIndex::class)->name('workbench.schedules.index');
        Route::get('/create', ScheduleForm::class)->name('workbench.schedules.create');
        Route::get('/{id}/edit', ScheduleForm::class)->name('workbench.schedules.edit');
    });

    Route::middleware(['role:admin'])->prefix('users')->group(function () {
        Route::get('/', UsersIndex::class)->name('workbench.users.index');
        Route::get('/create', UserForm::class)->name('workbench.users.create');
        Route::get('/{id}/edit', UserForm::class)->name('workbench.users.edit');
    });
});

Route::get('/workbench-manifest.json', function () {
    return response()->json([
        'name' => config('app.name'),
        'short_name' => config('app.name'),
        'start_url' => '/workbench',
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
})->name('workbench.manifest');

Route::get('/ide-auth-check', function () {
    return auth()->check() ? response('OK') : response('Unauthorized', 401);
})->middleware('web')->name('ide.auth-check');

Route::middleware(['auth'])->prefix('admin/github')->group(function () {
    Route::get('/redirect', [GitHubAuthController::class, 'redirect'])->name('github.redirect');
    Route::get('/callback', [GitHubAuthController::class, 'callback'])->name('github.callback');
    Route::delete('/disconnect', [GitHubAuthController::class, 'disconnect'])->name('github.disconnect');
});
