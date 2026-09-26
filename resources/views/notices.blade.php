@extends('layouts.app')
@section('title', 'Notices')
@section('content')
<div class="shell narrow-page"><span class="kicker">STAY IN THE LOOP</span><h1>Your <em>notices.</em></h1>@forelse($notices as $notice)<article class="list-row"><div><span class="kicker">{{ $notice->created_at->format('M j, Y') }}</span><h3>{{ $notice->data['title'] ?? 'Run update' }}</h3><p>{{ $notice->data['message'] ?? '' }}</p></div>@if(isset($notice->data['occurrence_id']))<a class="link-arrow" href="{{ route('runs.show', $notice->data['occurrence_id']) }}">View ↗</a>@endif</article>@empty<div class="empty"><h3>All caught up.</h3><p>Updates about your runs will appear here.</p></div>@endforelse{{ $notices->links() }}</div>
@endsection
