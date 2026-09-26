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
            'meeting_area' => 'required|string|max:255', 'safety_notes' => 'nullable|string|max:2000',
            'capacity' => 'required|integer|min:1|max:10000',
        ]);
        DB::transaction(function () use ($request, $occurrence, $data) {
            $locked = RunOccurrence::lockForUpdate()->findOrFail($occurrence->id);
            Gate::authorize('manage', $locked);
            $count = $locked->rsvps()->where('status', 'confirmed')->count();
            if ($data['capacity'] < $count) throw ValidationException::withMessages(['capacity' => 'Capacity cannot be below confirmed places.']);
            $old = $locked->meeting_area;
            $locked->update($data);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'occurrence.update', 'subject_type' => 'run_occurrence', 'subject_id' => $locked->id, 'metadata' => ['meeting_area_changed' => $old !== $locked->meeting_area]]);
            if ($old !== $locked->meeting_area && $locked->status === 'published') {
                foreach ($locked->rsvps()->with('user')->whereIn('status', ['confirmed', 'waitlisted'])->get() as $rsvp) {
                    $rsvp->user->notify(new RunNotice('Meeting area changed', $locked->run->title.' now meets at '.$locked->meeting_area.'.', $locked->id));
                }
            }
        }, 3);
        return back()->with('status', 'Run updated.');
    }

    public function publish(Request $request, RunOccurrence $occurrence)
    {
        Gate::authorize('manage', $occurrence);
        abort_unless($occurrence->status === 'draft' && $occurrence->cutoff_at_utc->isFuture(), 409);
        $occurrence->update(['status' => 'published']);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'occurrence.publish', 'subject_type' => 'run_occurrence', 'subject_id' => $occurrence->id]);
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
