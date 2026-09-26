<?php

namespace App\Policies;

use App\Models\RunOccurrence;
use App\Models\User;

class RunOccurrencePolicy
{
    public function view(?User $user, RunOccurrence $occurrence): bool
    {
        if ($occurrence->status === 'canceled' && $user) {
            return $occurrence->run->club->isOrganizer($user) || $occurrence->rsvps()->where('user_id', $user->id)->exists();
        }
        if ($occurrence->status !== 'published' || $occurrence->run->club->status !== 'active') {
            return false;
        }
        if ($occurrence->visibility === 'public' && $occurrence->run->club->visibility === 'public') {
            return true;
        }

        return $user && $occurrence->run->club->hasMember($user);
    }

    public function manage(User $user, RunOccurrence $occurrence): bool
    {
        return $occurrence->run->club->isOrganizer($user);
    }

    public function reserve(User $user, RunOccurrence $occurrence): bool
    {
        return $user->status === 'active' && $user->hasVerifiedEmail() && $this->view($user, $occurrence)
            && ($occurrence->visibility !== 'members' || $occurrence->run->club->hasMember($user));
    }
}
