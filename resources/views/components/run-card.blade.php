@props(['occurrence'])
<article class="run-card">
    <div class="run-card-top"><span class="eyebrow">{{ $occurrence->run->club->name }}</span><span class="pill">{{ $occurrence->run->club->city }}</span></div>
    <div class="run-card-date"><strong>{{ $occurrence->start_at_utc->timezone($occurrence->timezone)->format('d') }}</strong><span>{{ $occurrence->start_at_utc->timezone($occurrence->timezone)->format('M Y') }}</span></div>
    <h3><a href="{{ route('runs.show', $occurrence) }}">{{ $occurrence->run->title }}</a></h3>
    <p class="muted">{{ $occurrence->start_at_utc->timezone($occurrence->timezone)->format('D, g:i A') }} · {{ $occurrence->timezone }}</p>
    <div class="run-stats"><span><b>{{ $occurrence->distance_km }}</b> km</span><span><b>{{ gmdate('i:s', $occurrence->pace_min_seconds_per_km) }}</b> min/km</span></div>
    <div class="run-card-bottom"><span>{{ isset($occurrence->confirmed_count) && $occurrence->confirmed_count >= $occurrence->capacity ? 'Waitlist open' : 'Spots available' }}</span><a href="{{ route('runs.show', $occurrence) }}" aria-label="View {{ $occurrence->run->title }}">View run ↗</a></div>
</article>
