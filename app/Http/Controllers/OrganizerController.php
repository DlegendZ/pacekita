<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\Run;
use App\Models\RunOccurrence;
use App\Models\Rsvp;
use App\Notifications\RunNotice;
use App\Services\OccurrenceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrganizerController extends Controller
{
    public function index(Request $request)
    {
        $clubs = Club::whereHas('memberships', fn ($query) => $query->where('user_id', $request->user()->id)->where('status', 'active')->whereIn('role', ['organizer', 'owner']))->get();
        return view('organizer.index', compact('clubs'));
    }

    public function runs(Club $club)
    {
        Gate::authorize('manage', $club);
        return view('organizer.runs', ['club' => $club, 'runs' => $club->runs()->with(['occurrences' => fn ($query) => $query->orderBy('start_at_utc')])->latest()->get()]);
    }

    public function create(Club $club)
    {
        Gate::authorize('manage', $club);
        return view('organizer.create', compact('club'));
    }

    public function store(Request $request, Club $club, OccurrenceService $service)
    {
        Gate::authorize('manage', $club);
        $data = $request->validate([
            'title' => 'required|string|max:150', 'description' => 'required|string|max:3000',
            'terrain' => 'required|string|max:100', 'route_summary' => 'required|string|max:2000',
            'start_local' => 'required|date_format:Y-m-d\TH:i', 'timezone' => 'required|timezone',
            'duration_minutes' => 'required|integer|min:15|max:1440', 'distance_km' => 'required|numeric|min:0.1|max:500',
            'pace_min_seconds_per_km' => 'required|integer|min:120|max:1800',
            'pace_max_seconds_per_km' => 'required|integer|gte:pace_min_seconds_per_km|max:1800',
            'meeting_area' => 'required|string|max:255', 'safety_notes' => 'nullable|string|max:2000',
            'capacity' => 'required|integer|min:1|max:10000', 'cutoff_hours' => 'required|integer|min:0|max:168',
            'visibility' => 'required|in:public,members', 'repeat_until' => 'nullable|date_format:Y-m-d',
        ]);
        $start = CarbonImmutable::parse($data['start_local'], $data['timezone']);
        if ($start->utc()->subHours((int) $data['cutoff_hours'])->isPast()) throw ValidationException::withMessages(['start_local' => 'Start and registration cutoff must be in the future.']);
        if (! empty($data['repeat_until']) && CarbonImmutable::parse($data['repeat_until'], $data['timezone'])->endOfDay()->lt($start)) throw ValidationException::withMessages(['repeat_until' => 'End date must follow start.']);
        $occurrence = DB::transaction(function () use ($request, $club, $service, $data) {
            $run = $club->runs()->create($data + ['creator_id' => $request->user()->id]);
            return $service->create($run, $data);
        });
        return redirect()->route('organizer.occurrences.edit', $occurrence)->with('status', 'Draft saved. Preview then publish.');
    }

    public function edit(RunOccurrence $occurrence)
    {
        Gate::authorize('manage', $occurrence);
        $occurrence->load('run.club');
        return view('organizer.edit', compact('occurrence'));
    }

    public function update(Request $request, RunOccurrence $occurrence)
    {
        Gate::authorize('manage', $occurrence);
        abort_unless(in_array($occurrence->status, ['draft', 'published'], true) && $occurrence->start_at_utc->isFuture(), 409);
        $data = $request->validate([
            'start_local' => 'required|date_format:Y-m-d\TH:i', 'timezone' => 'required|timezone',
            'cutoff_hours' => 'required|integer|min:0|max:168', 'scope' => 'required|in:occurrence,future',
            'meeting_area' => 'required|string|max:255', 'safety_notes' => 'nullable|string|max:2000',
            'capacity' => 'required|integer|min:1|max:10000',
        ]);
        DB::transaction(function () use ($request, $occurrence, $data) {
            $selected = RunOccurrence::lockForUpdate()->findOrFail($occurrence->id);
            Gate::authorize('manage', $selected);
            $requested = CarbonImmutable::parse($data['start_local'], $data['timezone']);
            $oldLocal = $selected->start_at_utc->timezone($selected->timezone);
            $dayShift = (int) round($oldLocal->startOfDay()->diffInDays($requested->startOfDay(), false));
            $targets = $data['scope'] === 'future' && $selected->run->recurrence_rule
                ? $selected->run->occurrences()->where('start_at_utc', '>=', $selected->start_at_utc)->whereIn('status', ['draft', 'published'])->orderBy('id')->lockForUpdate()->get()
                : collect([$selected]);
            foreach ($targets as $target) {
                if ($target->start_at_utc->isPast()) continue;
                $count = $target->rsvps()->where('status', 'confirmed')->count();
                if ($data['capacity'] < $count) throw ValidationException::withMessages(['capacity' => 'Capacity cannot be below confirmed places.']);
                $local = $target->id === $selected->id ? $requested
                    : CarbonImmutable::parse($target->start_at_utc->timezone($target->timezone)->format('Y-m-d').' '.$requested->format('H:i'), $data['timezone'])->addDays($dayShift);
                $start = $local->utc();
                $cutoff = $start->subHours((int) $data['cutoff_hours']);
                if ($cutoff->isPast()) throw ValidationException::withMessages(['start_local' => 'Registration cutoff must be in the future.']);
                $changed = ! $target->start_at_utc->equalTo($start) || $target->meeting_area !== $data['meeting_area'];
                $target->update([
                    'start_at_utc' => $start, 'timezone' => $data['timezone'], 'cutoff_at_utc' => $cutoff,
                    'meeting_area' => $data['meeting_area'], 'safety_notes' => $data['safety_notes'] ?? null, 'capacity' => $data['capacity'],
                ]);
                AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'occurrence.update', 'subject_type' => 'run_occurrence', 'subject_id' => $target->id, 'metadata' => ['logistics_changed' => $changed]]);
                if ($changed && $target->status === 'published') {
                    foreach ($target->rsvps()->with('user')->whereIn('status', ['confirmed', 'waitlisted'])->get() as $rsvp) {
                        $rsvp->user->notify(new RunNotice('Run details changed', $target->run->title.' now starts '.$local->format('M j, g:i A').' '.$data['timezone'].' at '.$target->meeting_area.'.', $target->id));
                    }
                }
            }
            if ($data['scope'] === 'future' && $selected->run->recurrence_rule) {
                $selected->run->update(['recurrence_rule' => array_merge($selected->run->recurrence_rule, [
                    'start_local' => $requested->format('Y-m-d H:i:s'), 'timezone' => $data['timezone'],
                    'cutoff_hours' => (int) $data['cutoff_hours'],
                ])]);
            }
        }, 3);
        return back()->with('status', 'Run updated.');
    }

    public function publish(Request $request, RunOccurrence $occurrence)
    {
        Gate::authorize('manage', $occurrence);
        abort_unless($occurrence->status === 'draft' && $occurrence->cutoff_at_utc->isFuture(), 409);
        DB::transaction(function () use ($request, $occurrence) {
            $locked = RunOccurrence::lockForUpdate()->findOrFail($occurrence->id);
            Gate::authorize('manage', $locked);
            abort_unless($locked->status === 'draft' && $locked->cutoff_at_utc->isFuture(), 409);
            if ($locked->run->recurrence_rule) {
                $locked->run->occurrences()->where('status', 'draft')->where('cutoff_at_utc', '>', now())->update(['status' => 'published']);
            } else {
                $locked->update(['status' => 'published']);
            }
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'occurrence.publish', 'subject_type' => 'run_occurrence', 'subject_id' => $locked->id]);
        });
        return redirect()->route('runs.show', $occurrence)->with('status', 'Run published.');
    }

    public function cancel(Request $request, RunOccurrence $occurrence)
    {
        Gate::authorize('manage', $occurrence);
        $data = $request->validate(['reason' => 'required|string|min:10|max:1000']);
        DB::transaction(function () use ($request, $occurrence, $data) {
            $locked = RunOccurrence::lockForUpdate()->findOrFail($occurrence->id);
            Gate::authorize('manage', $locked);
            abort_if(in_array($locked->status, ['canceled', 'completed'], true), 409);
            $recipients = $locked->rsvps()->with('user')->whereIn('status', ['confirmed', 'waitlisted'])->get();
            $locked->update(['status' => 'canceled', 'cancellation_reason' => $data['reason']]);
            $locked->rsvps()->whereIn('status', ['confirmed', 'waitlisted'])->update(['status' => 'canceled']);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'occurrence.cancel', 'subject_type' => 'run_occurrence', 'subject_id' => $locked->id, 'metadata' => ['reason' => $data['reason']]]);
            foreach ($recipients as $rsvp) $rsvp->user->notify(new RunNotice('Run canceled', $locked->run->title.' was canceled: '.$data['reason'], $locked->id));
        }, 3);
        return redirect()->route('organizer.occurrences.edit', $occurrence)->with('status', 'Run canceled; attendees notified.');
    }

    public function announce(Request $request, RunOccurrence $occurrence)
    {
        Gate::authorize('manage', $occurrence);
        $data = $request->validate(['message' => 'required|string|min:10|max:2000']);
        DB::transaction(function () use ($request, $occurrence, $data) {
            $locked = RunOccurrence::lockForUpdate()->findOrFail($occurrence->id);
            Gate::authorize('manage', $locked);
            abort_unless($locked->status === 'published' && $locked->start_at_utc->isFuture(), 409);
            $update = $locked->updates()->create(['author_id' => $request->user()->id, 'message' => $data['message']]);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'occurrence.announce', 'subject_type' => 'run_update', 'subject_id' => $update->id]);
            foreach ($locked->rsvps()->with('user')->whereIn('status', ['confirmed', 'waitlisted'])->get() as $rsvp) {
                $rsvp->user->notify(new RunNotice('Update: '.$locked->run->title, $data['message'], $locked->id));
            }
        }, 3);

        return back()->with('status', 'Update posted and attendees notified.');
    }

    public function roster(RunOccurrence $occurrence)
    {
        Gate::authorize('manage', $occurrence);
        $rsvps = $occurrence->rsvps()->with('user.profile')->whereIn('status', ['confirmed', 'waitlisted', 'attended', 'absent'])->orderBy('waitlisted_at')->orderBy('id')->get();
        return view('organizer.roster', compact('occurrence', 'rsvps'));
    }

    public function checkIn(Request $request, RunOccurrence $occurrence, Rsvp $rsvp)
    {
        Gate::authorize('manage', $occurrence);
        abort_unless($rsvp->occurrence_id === $occurrence->id && $rsvp->status === 'confirmed', 403);
        $data = $request->validate(['status' => 'required|in:attended,absent']);
        $rsvp->update(['status' => $data['status'], 'checked_in_at' => now()]);
        return back()->with('status', 'Attendance recorded.');
    }
}
