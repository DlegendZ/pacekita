<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\Report;
use App\Models\RunOccurrence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    private function guard(Request $request): void { abort_unless($request->user()?->is_admin && $request->user()->status === 'active', 403); }

    public function index(Request $request)
    {
        $this->guard($request);
        return view('admin.index', [
            'reports' => Report::latest()->paginate(20), 'audits' => AuditLog::latest('created_at')->limit(30)->get(),
        ]);
    }

    public function review(Request $request, Report $report)
    {
        $this->guard($request);
        $data = $request->validate(['status' => 'required|in:reviewing,resolved,dismissed', 'reason' => 'required|string|min:5|max:1000']);
        DB::transaction(function () use ($request, $report, $data) {
            $report->update(['status' => $data['status'], 'reviewer_id' => $request->user()->id]);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'report.review', 'subject_type' => 'report', 'subject_id' => $report->id, 'metadata' => ['status' => $data['status'], 'reason' => $data['reason']]]);
        });
        return back()->with('status', 'Report reviewed.');
    }

    public function suspend(Request $request, User $user)
    {
        $this->guard($request);
        abort_if($user->id === $request->user()->id, 403);
        $data = $request->validate(['status' => 'required|in:active,suspended', 'reason' => 'required|string|min:5|max:1000']);
        DB::transaction(function () use ($request, $user, $data) {
            $user->update(['status' => $data['status']]);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'user.status', 'subject_type' => 'user', 'subject_id' => $user->id, 'metadata' => $data]);
        });
        return back()->with('status', 'Account updated.');
    }

    public function hideClub(Request $request, Club $club)
    {
        $this->guard($request);
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        DB::transaction(function () use ($request, $club, $data) {
            $club->update(['status' => 'hidden']);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'club.hide', 'subject_type' => 'club', 'subject_id' => $club->id, 'metadata' => $data]);
        });
        return back()->with('status', 'Club hidden.');
    }

    public function unpublish(Request $request, RunOccurrence $occurrence)
    {
        $this->guard($request);
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        DB::transaction(function () use ($request, $occurrence, $data) {
            $occurrence->update(['status' => 'canceled', 'cancellation_reason' => $data['reason']]);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'occurrence.unpublish', 'subject_type' => 'run_occurrence', 'subject_id' => $occurrence->id, 'metadata' => $data]);
        });
        return back()->with('status', 'Run unpublished.');
    }
}
