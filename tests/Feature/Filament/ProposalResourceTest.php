<?php

use App\Enums\ProposalPriority;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Filament\Resources\ProposalResource\Pages\ViewProposal;
use App\Models\Proposal;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can view proposal', function () {
    $proposal = Proposal::create([
        'title' => 'Proposal for testing',
        'description' => 'Ensure proposal view renders without errors.',
        'priority' => ProposalPriority::Medium,
        'status' => ProposalStatus::Pending,
        'project' => 'claude_runner',
        'type' => ProposalType::Other,
        'proposed_action' => [
            'target' => 'Test target',
        ],
    ]);

    livewire(ViewProposal::class, ['record' => $proposal->getRouteKey()])
        ->assertSuccessful();
});
