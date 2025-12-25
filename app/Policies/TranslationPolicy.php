<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use TomatoPHP\FilamentTranslations\Models\Translation;

class TranslationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Translation');
    }

    public function view(User $user, Translation $translation): bool
    {
        return $user->can('View:Translation');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Translation');
    }

    public function update(User $user, Translation $translation): bool
    {
        return $user->can('Update:Translation');
    }

    public function delete(User $user, Translation $translation): bool
    {
        return $user->can('Delete:Translation');
    }

    public function restore(User $user, Translation $translation): bool
    {
        return $user->can('Restore:Translation');
    }

    public function forceDelete(User $user, Translation $translation): bool
    {
        return $user->can('ForceDelete:Translation');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Translation');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Translation');
    }

    public function replicate(User $user, Translation $translation): bool
    {
        return $user->can('Replicate:Translation');
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Translation');
    }
}
