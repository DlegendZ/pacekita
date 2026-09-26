<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255', 'password' => 'required|min:12|confirmed']);
        $data['email'] = Str::lower($data['email']);
        if (User::where('email', $data['email'])->exists()) throw ValidationException::withMessages(['email' => 'Email already registered.']);
        $user = User::create($data);
        $user->profile()->create(['display_name' => $user->name]);
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('verification.notice');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt(['email' => Str::lower($data['email']), 'password' => $data['password'], 'status' => 'active'], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials or account suspended.']);
        }
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }

    public function verify(Request $request)
    {
        abort_unless($request->user()->id === (int) $request->route('id') && hash_equals($request->route('hash'), sha1($request->user()->getEmailForVerification())), 403);
        if (! $request->user()->hasVerifiedEmail()) $request->user()->markEmailAsVerified();
        return redirect()->route('dashboard')->with('status', 'Email verified.');
    }

    public function resend(Request $request)
    {
        if (! $request->user()->hasVerifiedEmail()) $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'Verification link sent.');
    }

    public function forgot(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink(['email' => Str::lower($request->email)]);
        return back()->with('status', 'If account exists, reset link has been sent.');
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => 'required|min:12|confirmed']);
        $result = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
        });
        return $result === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Password updated.')
            : back()->withErrors(['email' => __($result)]);
    }
}
