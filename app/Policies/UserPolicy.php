<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function view(?User $viewer, User $user): bool
    {
        return true;
    }

    public function update(User $viewer, User $user): bool
    {
        return $viewer->id === $user->id || $viewer->isSuperAdmin();
    }

    public function delete(User $viewer, User $user): bool
    {
        if ($viewer->id === $user->id) {
            return false;
        }

        // Cannot delete the last Super Admin
        if ($user->isSuperAdmin() && User::where('role', 'super_admin')->count() <= 1) {
            return false;
        }

        return $viewer->isSuperAdmin();
    }

    public function suspend(User $viewer, User $user): bool
    {
        if ($viewer->id === $user->id) {
            return false;
        }

        // Cannot suspend the last active Super Admin
        if ($user->isSuperAdmin() && User::where('role', 'super_admin')->where('is_suspended', false)->count() <= 1) {
            return false;
        }

        return $viewer->isSuperAdmin();
    }

    public function managePlatform(User $viewer): bool
    {
        return $viewer->canManagePlatform();
    }
}
