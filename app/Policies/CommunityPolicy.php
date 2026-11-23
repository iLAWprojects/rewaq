<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommunityPolicy
{
    use HandlesAuthorization;

    public function view(?User $user, Community $community): bool
    {
        if (in_array($community->type, ['public', 'open', 'institutional', 'specialized', 'book_of_month']) || ($user && $user->isSuperAdmin())) {
            return true;
        }

        return $user && $community->userRole($user) !== null;
    }

    public function create(User $user): bool
    {
        return ! $user->is_suspended;
    }

    public function update(User $user, Community $community): bool
    {
        if ($user->is_suspended) {
            return false;
        }

        return $user->isSuperAdmin() || $community->isOwner($user);
    }

    public function delete(User $user, Community $community): bool
    {
        if ($user->is_suspended) {
            return false;
        }

        return $user->isSuperAdmin() || $community->isOwner($user);
    }

    public function manageMembers(User $user, Community $community): bool
    {
        if ($user->is_suspended) {
            return false;
        }

        return $user->isSuperAdmin() || $community->isManager($user);
    }

    public function coordinateReading(User $user, Community $community): bool
    {
        if ($user->is_suspended) {
            return false;
        }

        return $user->isSuperAdmin() || $community->isReadingCoordinator($user);
    }

    public function postDiscussion(User $user, Community $community): bool
    {
        return $community->canPostDiscussion($user);
    }

    public function votePoll(User $user, Community $community): bool
    {
        return $community->canVotePoll($user);
    }

    public function transferOwnership(User $user, Community $community): bool
    {
        if ($user->is_suspended) {
            return false;
        }

        return $user->isSuperAdmin() || $community->isOwner($user);
    }

    public function leave(User $user, Community $community): bool
    {
        // منع آخر مالك من المغادرة قبل نقل الملكية
        return ! $community->isOnlyOwner($user);
    }

    public function invite(User $user, Community $community): bool
    {
        if ($user->is_suspended) {
            return false;
        }
        if ($user->isSuperAdmin() || $community->isManager($user)) {
            return true;
        }

        return $community->allow_member_invites && $community->isMember($user);
    }

    public function createChallenge(User $user, Community $community): bool
    {
        if ($user->is_suspended) {
            return false;
        }
        if ($user->isSuperAdmin() || $community->isManager($user)) {
            return true;
        }

        return $community->allow_member_challenges && $community->isMember($user);
    }

    public function createMeeting(User $user, Community $community): bool
    {
        if ($user->is_suspended) {
            return false;
        }
        if ($user->isSuperAdmin() || $community->isManager($user)) {
            return true;
        }

        return $community->allow_member_meetings && $community->isMember($user);
    }
}
