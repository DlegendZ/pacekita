@extends('layouts.app')
@section('title', 'Set new password')
@section('content')
<div class="shell narrow-page"><span class="kicker">ACCOUNT HELP</span><h1>New password.</h1><form class="form-card form-stack" method="post" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><label>Email<input type="email" name="email" value="{{ request('email') }}" required></label><label>New password<input type="password" name="password" required minlength="12"></label><label>Confirm password<input type="password" name="password_confirmation" required></label><button class="button button-dark" type="submit">Update password ↗</button></form></div>
@endsection
