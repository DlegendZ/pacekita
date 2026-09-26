@extends('layouts.app')
@section('title', 'Verify email')
@section('content')
<div class="shell narrow-page"><span class="kicker">ONE QUICK STEP</span><h1>Check your inbox.</h1><p>Verify your email to RSVP and create clubs. Check spam if message doesn’t arrive.</p><form method="post" action="{{ route('verification.send') }}">@csrf<button class="button button-dark" type="submit">Resend verification ↗</button></form></div>
@endsection
