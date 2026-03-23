<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;
use Spatie\Permission\Models\Role;

class Form extends Component
{
    use Toast;

    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->user = User::findOrFail($id);
            $this->name = $this->user->name;
            $this->email = $this->user->email;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function roleOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($role) => ['id' => $role->name, 'name' => $role->name])
            ->toArray();
    }

    public function save(): void
    {
        $isEditing = $this->user !== null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'.($isEditing ? ",{$this->user->id}" : '')],
            'password' => $isEditing ? ['nullable', 'string', 'min:8', 'same:passwordConfirmation'] : ['required', 'string', 'min:8', 'same:passwordConfirmation'],
            'passwordConfirmation' => ['nullable', 'string'],
        ];

        $validated = $this->validate($rules);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (filled($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        if ($isEditing) {
            $this->user->update($data);
            $this->success('User updated.');
        } else {
            User::create($data);
            $this->success('User created.');
        }

        $this->redirect(route('workbench.users.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.users.form', [
            'roleOptions' => $this->roleOptions(),
        ]);
    }
}
