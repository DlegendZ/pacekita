@extends('layouts.app')
@section('title', $occurrence->run->title)
@section('content')
<section class="detail-hero">
    <div class="shell">
        <a class="back-link" href="{{ route('runs.index') }}">← All runs</a>
        <div class="detail-grid">
            <div>
                <span class="kicker">{{ $occurrence->run->club->city }} · {{ $occurrence->run->club->name }}</span>
                <h1>{{ $occurrence->run->title }}</h1>
                <p>{{ $occurrence->run->description }}</p>
                @if($occurrence->status === 'canceled')
                    <div class="status-box">Canceled: {{ $occurrence->cancellation_reason }}</div>
                @endif
            </div>
            <div class="detail-date">
                <strong>{{ $occurrence->start_at_utc->timezone($occurrence->timezone)->format('d') }}</strong>
                <span>{{ $occurrence->start_at_utc->timezone($occurrence->timezone)->format('F Y') }}</span>
                <small>{{ $occurrence->start_at_utc->timezone($occurrence->timezone)->format('l, g:i A') }} {{ $occurrence->timezone }}</small>
            </div>
        </div>
    </div>
</section>
<div class="shell detail-body">
    <div class="detail-main">
        <div class="fact-grid">
            <div><span>DISTANCE</span><b>{{ $occurrence->distance_km }} km</b></div>
            <div><span>PACE RANGE</span><b>{{ gmdate('i:s', $occurrence->pace_min_seconds_per_km) }}–{{ gmdate('i:s', $occurrence->pace_max_seconds_per_km) }} /km</b></div>
            <div><span>DURATION</span><b>{{ $occurrence->duration_minutes }} min</b></div>
            <div><span>TERRAIN</span><b>{{ $occurrence->run->terrain }}</b></div>
        </div>
        <h2>The route</h2><p>{{ $occurrence->run->route_summary }}</p>
        <h2>Where to meet</h2><p>{{ $occurrence->meeting_area }}</p>
        <h2>Good to know</h2><p>{{ $occurrence->safety_notes ?: 'Bring water, check conditions, and look out for one another.' }}</p>
        <p>Hosted by <a href="{{ route('clubs.show', $occurrence->run->club) }}">{{ $occurrence->run->club->name }}</a>.</p>
    </div>
    <aside class="booking-box">
        <span class="kicker">YOUR PLACE AT THE START</span>
        <h2>{{ $occurrence->status === 'canceled' ? 'Run canceled' : ($occurrence->confirmed_count >= $occurrence->capacity ? 'Waitlist open' : 'Spots available') }}</h2>
        <p>{{ $occurrence->confirmed_count }} of {{ $occurrence->capacity }} places taken. Registration closes {{ $occurrence->cutoff_at_utc->timezone($occurrence->timezone)->format('M j, g:i A') }} {{ $occurrence->timezone }}.</p>
        @if($rsvp && in_array($rsvp->status, ['confirmed', 'waitlisted']))
            <div class="status-box">Your status: <strong>{{ ucfirst($rsvp->status) }}</strong></div>
            <form method="post" action="{{ route('rsvps.destroy', $occurrence) }}" onsubmit="return confirm('Withdraw from this run?')">
                @csrf @method('DELETE')
                <button class="button button-outline-dark" type="submit">Withdraw RSVP</button>
            </form>
        @elseif($rsvp && $rsvp->status === 'canceled')
            <div class="status-box">Your RSVP was canceled.</div>
        @elseif($occurrence->isOpen())
            @auth
                @if(auth()->user()->hasVerifiedEmail())
                    <form method="post" action="{{ route('rsvps.store', $occurrence) }}">
                        @csrf
                        <button class="button button-lime" type="submit">{{ $occurrence->confirmed_count >= $occurrence->capacity ? 'Join waitlist' : 'Save my spot' }} ↗</button>
                    </form>
                @else
                    <a class="button button-dark" href="{{ route('verification.notice') }}">Verify email first ↗</a>
                @endif
            @else
                <a class="button button-lime" href="{{ route('login') }}">Log in to RSVP ↗</a>
            @endauth
        @else
            <p class="status-box">Registration closed.</p>
        @endif
    </aside>
</div>
@auth
    <div class="shell report-block"><details>
        <summary>Report a concern with this run</summary>
        <form method="post" action="{{ route('reports.store') }}">
            @csrf
            <input type="hidden" name="target_type" value="run">
            <input type="hidden" name="target_id" value="{{ $occurrence->id }}">
            <label>What happened?<textarea name="reason" required minlength="10" maxlength="2000"></textarea></label>
            <button class="button button-dark" type="submit">Send report</button>
        </form>
    </details></div>
@endauth
@endsection
