@extends('layouts.app')
@section('title', 'Reset password')
@section('content')
<div class="shell narrow-page"><span class="kicker">ACCOUNT HELP</span><h1>Reset password.</h1><p>Enter your email. If we know it, we’ll send a reset link.</p><form class="form-card form-stack" method="post" action="{{ route('password.email') }}">@csrf<label>Email address<input type="email" name="email" required autocomplete="email"></label><button class="button button-dark" type="submit">Send reset link ↗</button></form></div>
@endsection
