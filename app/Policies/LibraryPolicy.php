<?php

namespace App\Policies;

use App\Models\Library;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LibraryPolicy
{
    use HandlesAuthorization;

    public function view(?User $user, Library $library): bool
    {
        return $library->canViewCatalog($user);
    }

    public function create(User $user): bool
    {
        return ! $user->is_suspended;
    }

    public function update(User $user, Library $library): bool
    {
        if ($user->is_suspended) {
            return false;
        }

        return $user->isSuperAdmin() || $library->isOwner($user);
    }

    public function delete(User $user, Library $library): bool
    {
        if ($user->is_suspended) {
            return false;
        }

        return $user->isSuperAdmin() || $library->isOwner($user);
    }

    public function manageMembers(User $user, Library $library): bool
    {
        if ($user->is_suspended) {
            return false;
        }

        return $user->isSuperAdmin() || $library->canManage($user);
    }

    public function manageBooks(User $user, Library $library): bool
    {
        if ($user->is_suspended) {
            return false;
        }

        return $user->isSuperAdmin() || $library->canManage($user);
    }

    public function borrow(User $user, Library $library): bool
    {
        if ($user->is_suspended) {
            return false;
        }

        return $library->canBorrow($user);
    }

    public function transferOwnership(User $user, Library $library): bool
    {
        if ($user->is_suspended) {
            return false;
        }

        return $user->isSuperAdmin() || $library->isOwner($user);
    }

    public function leave(User $user, Library $library): bool
    {
        // منع آخر مالك من المغادرة قبل نقل الملكية
        return ! $library->isOnlyOwner($user);
    }
}
