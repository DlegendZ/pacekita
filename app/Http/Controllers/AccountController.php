<?php

namespace App\Http\Controllers;

use App\Models\Rsvp;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function dashboard(Request $request)
    {
        $rsvps = Rsvp::with('occurrence.run.club')->where('user_id', $request->user()->id)->latest()->get();
        $memberships = $request->user()->memberships()->with('club')->whereIn('status', ['active', 'pending'])->get();
        return view('dashboard', compact('rsvps', 'memberships'));
    }

    public function profile(Request $request) { return view('profile', ['profile' => $request->user()->profile]); }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'display_name' => 'required|string|max:100', 'city' => 'nullable|string|max:100',
            'preferred_distance_km' => 'nullable|numeric|min:0.1|max:500',
            'preferred_pace_seconds_per_km' => 'nullable|integer|min:120|max:1800',
            'bio' => 'nullable|string|max:1000', 'emergency_contact' => 'nullable|string|max:500',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        unset($data['photo']);
        if ($request->hasFile('photo')) $data['photo_path'] = $request->file('photo')->store('profiles', 'public');
        $request->user()->profile()->updateOrCreate([], $data);
        return back()->with('status', 'Profile saved.');
    }

    public function notices(Request $request)
    {
        $notices = $request->user()->notifications()->latest()->paginate(20);
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return view('notices', compact('notices'));
    }
}
