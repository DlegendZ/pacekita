<?php

namespace App\Services;

use App\Models\Rsvp;
use App\Models\RunOccurrence;
use App\Models\User;
use App\Notifications\RunNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RsvpService
{
    public function reserve(User $user, RunOccurrence $occurrence): Rsvp
    {
        return DB::transaction(function () use ($user, $occurrence) {
            $locked = RunOccurrence::query()->lockForUpdate()->findOrFail($occurrence->id);
            Gate::forUser($user)->authorize('reserve', $locked);
            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['rsvp' => 'Registration has closed.']);
            }

            $existing = Rsvp::query()->where('occurrence_id', $locked->id)->where('user_id', $user->id)->first();
            if ($existing && in_array($existing->status, ['confirmed', 'waitlisted'], true)) {
                return $existing;
            }
            if ($existing && in_array($existing->status, ['attended', 'absent', 'canceled'], true)) {
                throw ValidationException::withMessages(['rsvp' => 'This RSVP can no longer be changed.']);
            }

            $confirmed = $locked->rsvps()->where('status', 'confirmed')->count();
            $status = $confirmed < $locked->capacity ? 'confirmed' : 'waitlisted';
            $rsvp = $existing ?? new Rsvp(['occurrence_id' => $locked->id, 'user_id' => $user->id]);
            $rsvp->status = $status;
            $rsvp->confirmed_at = $status === 'confirmed' ? now() : null;
            $rsvp->waitlisted_at = $status === 'waitlisted' ? now() : null;
            $rsvp->save();
            $user->notify(new RunNotice('Run RSVP '.$status, 'Your place for '.$locked->run->title.' is '.$status.'.', $locked->id));

            return $rsvp;
        }, 3);
    }

    public function withdraw(User $user, RunOccurrence $occurrence): void
    {
        DB::transaction(function () use ($user, $occurrence) {
            $locked = RunOccurrence::query()->lockForUpdate()->findOrFail($occurrence->id);
            $rsvp = $locked->rsvps()->where('user_id', $user->id)->firstOrFail();
            if (! in_array($rsvp->status, ['confirmed', 'waitlisted'], true)) {
                return;
            }
            $wasConfirmed = $rsvp->status === 'confirmed';
            $rsvp->update(['status' => 'withdrawn']);
            if (! $wasConfirmed || ! $locked->isOpen()) {
                return;
            }

            $waiters = $locked->rsvps()->with('user')->where('status', 'waitlisted')
                ->orderBy('waitlisted_at')->orderBy('id')->lockForUpdate()->get();
            foreach ($waiters as $waiter) {
                if ($waiter->user->status !== 'active' || ! $waiter->user->hasVerifiedEmail()
                    || ! Gate::forUser($waiter->user)->allows('reserve', $locked)) {
                    continue;
                }
                $waiter->update(['status' => 'confirmed', 'confirmed_at' => now()]);
                $waiter->user->notify(new RunNotice('You have a place', 'A place opened for '.$locked->run->title.'. You are confirmed.', $locked->id));
                break;
            }
        }, 3);
    }
}
