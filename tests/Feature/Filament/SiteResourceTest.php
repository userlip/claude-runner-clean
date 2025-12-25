<?php

use App\Filament\Resources\SiteResource\Pages\CreateSite;
use App\Filament\Resources\SiteResource\Pages\ListSites;
use App\Models\Repository;
use App\Models\Site;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can view sites list', function () {
    Livewire::test(ListSites::class)
        ->assertSuccessful();
});

test('only shows sites from own repositories', function () {
    $ownRepo = Repository::factory()->create(['user_id' => $this->user->id]);
    $ownSite = Site::factory()->create(['repository_id' => $ownRepo->id]);

    $otherRepo = Repository::factory()->create();
    $otherSite = Site::factory()->create(['repository_id' => $otherRepo->id]);

    Livewire::test(ListSites::class)
        ->assertCanSeeTableRecords([$ownSite])
        ->assertCanNotSeeTableRecords([$otherSite]);
});

test('can create site', function () {
    Queue::fake();

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);

    Livewire::test(CreateSite::class)
        ->fillForm([
            'repository_id' => $repository->id,
            'domain' => 'test-site.marin.sh',
            'php_version' => '8.4',
            'web_directory' => '/public',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Site::where('domain', 'test-site.marin.sh')->exists())->toBeTrue();
});

test('domain must be unique', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    Site::factory()->create(['domain' => 'existing.marin.sh']);

    Livewire::test(CreateSite::class)
        ->fillForm([
            'repository_id' => $repository->id,
            'domain' => 'existing.marin.sh',
        ])
        ->call('create')
        ->assertHasFormErrors(['domain' => 'unique']);
});
