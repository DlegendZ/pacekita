<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\RunOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DiscoveryController extends Controller
{
    public function home()
    {
        $upcoming = $this->publicRuns()->limit(3)->get();

        return view('home', compact('upcoming'));
    }

    private function publicRuns()
    {
        return RunOccurrence::query()->with('run.club')->withCount(['rsvps as confirmed_count' => fn ($query) => $query->where('status', 'confirmed')])
            ->where('status', 'published')->where('visibility', 'public')->where('start_at_utc', '>', now())
            ->whereHas('run.club', fn ($query) => $query->where('visibility', 'public')->where('status', 'active'))
            ->orderBy('start_at_utc');
    }

    public function runs(Request $request)
    {
        $filters = $request->validate([
            'city' => 'nullable|string|max:100', 'date' => 'nullable|date_format:Y-m-d',
            'distance' => 'nullable|numeric|min:0.1|max:500', 'pace' => 'nullable|integer|min:120|max:1800',
            'club' => 'nullable|integer|exists:clubs,id',
            'timezone' => 'nullable|timezone',
        ]);
        $query = $this->publicRuns();
        if (! empty($filters['city'])) {
            $query->whereHas('run.club', fn ($q) => $q->whereRaw('LOWER(clubs.city) LIKE ?', ['%'.mb_strtolower($filters['city']).'%']));
        }
        if (! empty($filters['date'])) {
            $timezone = $filters['timezone'] ?? 'Asia/Jakarta';
            $start = CarbonImmutable::parse($filters['date'].' 00:00:00', $timezone);
            $query->where('start_at_utc', '>=', $start->utc())
                ->where('start_at_utc', '<', $start->addDay()->utc());
        }
        if (! empty($filters['distance'])) {
            $query->where('distance_km', $filters['distance']);
        }
        if (! empty($filters['pace'])) {
            $query->where('pace_min_seconds_per_km', '<=', $filters['pace'])->where('pace_max_seconds_per_km', '>=', $filters['pace']);
        }
        if (! empty($filters['club'])) {
            $query->whereHas('run', fn ($q) => $q->where('club_id', $filters['club']));
        }

        return view('runs.index', ['occurrences' => $query->paginate(12)->withQueryString(), 'clubs' => Club::where('status', 'active')->where('visibility', 'public')->orderBy('name')->get(), 'filters' => $filters]);
    }

    public function run(RunOccurrence $occurrence)
    {
        $occurrence->load('run.club', 'run.creator', 'updates');
        abort_unless(Gate::allows('view', $occurrence), 404);
        $occurrence->loadCount(['rsvps as confirmed_count' => fn ($query) => $query->where('status', 'confirmed')]);
        $rsvp = auth()->check() ? $occurrence->rsvps()->where('user_id', auth()->id())->first() : null;

        return view('runs.show', compact('occurrence', 'rsvp'));
    }

    public function clubs()
    {
        return view('clubs.index', ['clubs' => Club::where('visibility', 'public')->where('status', 'active')->orderBy('name')->paginate(12)]);
    }

    public function club(Club $club)
    {
        abort_unless(Gate::allows('view', $club), 404);
        $occurrences = RunOccurrence::with('run.club')->whereHas('run', fn ($query) => $query->where('club_id', $club->id))
            ->where('status', 'published')->where('start_at_utc', '>', now())
            ->where(fn ($query) => $query->where('visibility', 'public')->when(auth()->check() && $club->hasMember(auth()->user()), fn ($q) => $q->orWhere('visibility', 'members')))
            ->orderBy('start_at_utc')->get();
        $membership = auth()->check() ? $club->memberships()->where('user_id', auth()->id())->first() : null;

        return view('clubs.show', compact('club', 'occurrences', 'membership'));
    }
}
