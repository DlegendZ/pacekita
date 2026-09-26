<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;

class ClubPolicy
{
    public function view(?User $user, Club $club): bool
    {
        return $club->status === 'active' && ($club->visibility === 'public' || ($user && $club->hasMember($user)));
    }

    public function manage(User $user, Club $club): bool
    {
        return $club->status === 'active' && $club->isOrganizer($user);
    }

    public function join(User $user, Club $club): bool
    {
        return $club->status === 'active' && $user->status === 'active' && $user->hasVerifiedEmail() && $club->visibility === 'public';
    }
}
