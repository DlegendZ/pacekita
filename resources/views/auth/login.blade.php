@extends('layouts.app')
@section('title', 'Log in')
@section('content')
<div class="auth-page shell"><div class="auth-intro"><span class="kicker">WELCOME BACK</span><h1>Ready to<br><em>move?</em></h1><p>Your crew is waiting at the start line.</p></div><div class="form-card"><h2>Log in</h2><p>New here? <a href="{{ route('register') }}">Create account</a></p><form method="post" action="{{ route('login') }}" class="form-stack">@csrf<label>Email address<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><label class="check-label"><input type="checkbox" name="remember" value="1"> Remember me</label><button class="button button-lime" type="submit">Log in ↗</button></form><a class="small-link" href="{{ route('password.request') }}">Forgot password?</a></div></div>
@endsection
