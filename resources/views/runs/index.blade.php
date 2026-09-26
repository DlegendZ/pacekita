@extends('layouts.app')
@section('title', 'Find runs')
@section('content')
<section class="page-hero shell">
    <span class="kicker">THE RUN BOARD</span><h1>Find your <em>next run.</em></h1>
    <p>Find a pace, place, and people that feel like yours.</p>
</section>
<div class="shell listing-layout">
    <aside class="filter-panel">
        <h2>Filter runs <span>↘</span></h2>
        <form method="get" action="{{ route('runs.index') }}" class="form-stack">
            <label>City<input name="city" value="{{ $filters['city'] ?? '' }}" placeholder="Any city"></label>
            <label>Date<input type="date" name="date" value="{{ $filters['date'] ?? '' }}"></label>
            <label>Date timezone
                <select name="timezone">
                    @foreach(['Asia/Jakarta' => 'Jakarta (WIB)', 'Asia/Makassar' => 'Makassar (WITA)', 'Asia/Jayapura' => 'Jayapura (WIT)', 'UTC' => 'UTC'] as $zone => $label)
                        <option value="{{ $zone }}" @selected(($filters['timezone'] ?? 'Asia/Jakarta') === $zone)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Distance (km)<input type="number" min="0.1" max="500" step="0.1" name="distance" value="{{ $filters['distance'] ?? '' }}" placeholder="Any distance"></label>
            <label>Pace (seconds / km)<input type="number" min="120" max="1800" name="pace" value="{{ $filters['pace'] ?? '' }}" placeholder="e.g. 360 for 6:00"></label>
            <label>Club<select name="club"><option value="">All clubs</option>@foreach($clubs as $club)<option value="{{ $club->id }}" @selected(($filters['club'] ?? '') == $club->id)>{{ $club->name }}</option>@endforeach</select></label>
            <button class="button button-dark" type="submit">Apply filters ↗</button>
            <a class="small-link" href="{{ route('runs.index') }}">Clear filters</a>
        </form>
    </aside>
    <div class="listing-results">
        <div class="results-head"><h2>Upcoming runs</h2><span>{{ $occurrences->total() }} found</span></div>
        @if($occurrences->isEmpty())
            <div class="empty"><h3>No runs match yet.</h3><p>Try a different city, date or pace, or browse all upcoming runs.</p><a class="button button-dark" href="{{ route('runs.index') }}">Show all runs ↗</a></div>
        @else
            <div class="card-grid">@foreach($occurrences as $occurrence)<x-run-card :occurrence="$occurrence" />@endforeach</div>
            {{ $occurrences->links() }}
        @endif
    </div>
</div>
@endsection
