@extends('layouts.app')
@section('title', 'Moderation')
@section('content')
<section class="page-hero shell">
    <span class="kicker">ADMINISTRATION</span><h1>Keep it <em>safe.</em></h1>
    <p>Review concerns and keep an audit trail.</p>
</section>
<div class="shell dashboard-grid">
    <section>
        <h2>Reports</h2>
        @forelse($reports as $report)
            <article class="list-row"><div>
                <span class="kicker">{{ strtoupper($report->status) }} · {{ $report->target_type }} #{{ $report->target_id }}</span>
                <h3>Report #{{ $report->id }}</h3><p>{{ $report->reason }}</p>
                <form class="inline-form" method="post" action="{{ route('admin.reports.review', $report) }}">
                    @csrf @method('PATCH')
                    <select name="status" aria-label="Review status"><option value="reviewing">Reviewing</option><option value="resolved">Resolved</option><option value="dismissed">Dismissed</option></select>
                    <input name="reason" required minlength="5" placeholder="Decision reason" aria-label="Decision reason">
                    <button class="button button-dark" type="submit">Save</button>
                </form>
            </div></article>
        @empty
            <div class="empty"><h3>No reports.</h3><p>Nothing needs review right now.</p></div>
        @endforelse
        {{ $reports->links() }}
    </section>
    <aside><h2>Recent audit</h2>
        @foreach($audits as $audit)
            <div class="side-row"><span>{{ $audit->action }} · {{ $audit->subject_type }} #{{ $audit->subject_id }}</span><small>{{ $audit->created_at->format('M j, H:i') }}</small></div>
        @endforeach
    </aside>
</div>
<section class="shell section">
    <h2>Deletion requests</h2>
    @forelse($deletionRequests as $deletionRequest)
        <div class="list-row">User #{{ $deletionRequest->user_id }} requested deletion {{ $deletionRequest->requested_at }}. Review retention before acting.</div>
    @empty
        <p>No pending requests.</p>
    @endforelse
</section>
<section class="shell section">
    <h2>Moderation actions</h2>
    <div class="card-grid">
        <form class="form-card form-stack" method="post" action="{{ route('admin.actions') }}">
            @csrf @method('PATCH')<input type="hidden" name="kind" value="user">
            <h3>Account</h3><label>User ID<input type="number" min="1" name="target_id" required></label>
            <label>Status<select name="status"><option value="suspended">Suspend</option><option value="active">Restore</option></select></label>
            <label>Reason<input name="reason" minlength="5" required></label><button class="button button-dark" type="submit">Update account</button>
        </form>
        <form class="form-card form-stack" method="post" action="{{ route('admin.actions') }}">
            @csrf @method('PATCH')<input type="hidden" name="kind" value="club">
            <h3>Club</h3><label>Club slug<input name="target_id" required></label>
            <label>Reason<input name="reason" minlength="5" required></label><button class="button button-dark" type="submit">Hide club</button>
        </form>
        <form class="form-card form-stack" method="post" action="{{ route('admin.actions') }}">
            @csrf @method('PATCH')<input type="hidden" name="kind" value="run">
            <h3>Run</h3><label>Occurrence ID<input type="number" min="1" name="target_id" required></label>
            <label>Reason<input name="reason" minlength="5" required></label><button class="button button-dark" type="submit">Unpublish run</button>
        </form>
    </div>
</section>
@endsection
