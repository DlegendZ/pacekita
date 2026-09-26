<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Report;
use App\Models\RunOccurrence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['target_type' => 'required|in:club,run,user', 'target_id' => 'required|integer|min:1', 'reason' => 'required|string|min:10|max:2000']);
        $target = match ($data['target_type']) {
            'club' => Club::findOrFail($data['target_id']),
            'run' => RunOccurrence::findOrFail($data['target_id']),
            'user' => User::findOrFail($data['target_id']),
        };
        if ($target instanceof Club || $target instanceof RunOccurrence) abort_unless(Gate::allows('view', $target), 404);
        Report::create($data + ['reporter_id' => $request->user()->id]);
        return back()->with('status', 'Report received. Thank you.');
    }
}
