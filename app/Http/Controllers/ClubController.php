<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ClubController extends Controller
{
    public function create()
    {
        return view('clubs.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'city' => 'required|string|max:100',
            'description' => 'required|string|max:3000', 'visibility' => 'required|in:public,private',
            'join_policy' => 'required|in:open,approval',
        ]);
        $club = DB::transaction(function () use ($request, $data) {
            $club = Club::create($data + ['owner_id' => $request->user()->id, 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6))]);
            $club->memberships()->create(['user_id' => $request->user()->id, 'role' => 'owner', 'status' => 'active', 'requested_at' => now(), 'decided_at' => now()]);

            return $club;
        });

        return redirect()->route('clubs.show', $club)->with('status', 'Club created.');
    }

    public function join(Request $request, Club $club)
    {
        Gate::authorize('join', $club);
        $membership = DB::transaction(function () use ($club, $request) {
            $locked = Club::lockForUpdate()->findOrFail($club->id);
            $status = $locked->join_policy === 'open' ? 'active' : 'pending';
            $membership = $locked->memberships()->firstOrNew(['user_id' => $request->user()->id]);
            if ($membership->exists && in_array($membership->status, ['active', 'pending'], true)) {
                return $membership;
            }
            $membership->fill(['role' => 'member', 'status' => $status, 'requested_at' => now(), 'decided_at' => $status === 'active' ? now() : null])->save();

            return $membership;
        }, 3);

        return back()->with('status', $membership->status === 'active' ? 'Joined club.' : 'Request sent.');
    }

    public function manage(Club $club)
    {
        Gate::authorize('manage', $club);

        return view('organizer.club', ['club' => $club, 'memberships' => $club->memberships()->with('user.profile')->orderBy('requested_at')->get()]);
    }

    public function settings(Request $request, Club $club)
    {
        Gate::authorize('manage', $club);
        $data = $request->validate([
            'name' => 'required|string|max:120', 'city' => 'required|string|max:100', 'description' => 'required|string|max:3000',
            'visibility' => 'required|in:public,private', 'join_policy' => 'required|in:open,approval',
        ]);
        $club->update($data);

        return back()->with('status', 'Club updated.');
    }

    public function decide(Request $request, Club $club, ClubMembership $membership)
    {
        Gate::authorize('manage', $club);
        abort_unless($membership->club_id === $club->id, 404);
        $data = $request->validate(['status' => 'required|in:active,rejected,removed', 'role' => 'nullable|in:member,organizer']);
        abort_if($membership->role === 'owner' || ($data['status'] === 'removed' && $membership->user_id === $club->owner_id), 403);
        DB::transaction(function () use ($request, $membership, $data) {
            $membership->update(['status' => $data['status'], 'role' => $data['role'] ?? $membership->role, 'decided_at' => now()]);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'membership.decide', 'subject_type' => 'club_membership', 'subject_id' => $membership->id, 'metadata' => ['status' => $data['status']]]);
        });

        return back()->with('status', 'Membership updated.');
    }
}
