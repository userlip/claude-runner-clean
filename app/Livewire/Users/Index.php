<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;
    use WithPagination;

    public string $search = '';

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        User::findOrFail($id)->delete();
        $this->success('User deleted.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'roles_list', 'label' => 'Roles', 'sortable' => false],
            ['key' => 'created_at', 'label' => 'Created'],
        ];
    }

    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with(['roles'])
            ->when(
                $this->search,
                fn ($q) => $q->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                })
            )
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function render(): View
    {
        $users = $this->users()->through(function (User $user) {
            $user->roles_list = $user->roles->pluck('name')->join(', ') ?: '-';

            return $user;
        });

        return view('livewire.users.index', [
            'users' => $users,
            'headers' => $this->headers(),
        ]);
    }
}
