<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MeetingPolicy
{
    use HandlesAuthorization;

    public function view(?User $user, Meeting $meeting): bool
    {
        if (! $meeting->community) {
            return true;
        }
        if ($meeting->community->type === 'public' || ($user && $user->isSuperAdmin())) {
            return true;
        }

        return $user && $meeting->community->userRole($user) !== null;
    }

    public function create(User $user, Community $community): bool
    {
        if ($user->is_suspended) {
            return false;
        }

        return $user->isSuperAdmin() || $community->isManager($user);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->is_suspended) {
            return false;
        }
        if ($user->isSuperAdmin() || $meeting->coordinator_id === $user->id) {
            return true;
        }

        return $meeting->community && $meeting->community->isManager($user);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        if ($user->is_suspended) {
            return false;
        }
        if ($user->isSuperAdmin() || $meeting->coordinator_id === $user->id) {
            return true;
        }

        return $meeting->community && $meeting->community->isManager($user);
    }

    public function rsvp(User $user, Meeting $meeting): bool
    {
        if ($user->is_suspended) {
            return false;
        }
        if ($meeting->community && $meeting->community->isSuspended($user)) {
            return false;
        }

        return true;
    }

    public function checkIn(User $user, Meeting $meeting): bool
    {
        if ($user->is_suspended) {
            return false;
        }
        if ($user->isSuperAdmin() || $meeting->coordinator_id === $user->id) {
            return true;
        }

        return $meeting->community && $meeting->community->isManager($user);
    }
}
