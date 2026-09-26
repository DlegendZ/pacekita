<?php

namespace App\Http\Controllers;

use App\Models\RunOccurrence;
use App\Services\RsvpService;
use Illuminate\Http\Request;

class RsvpController extends Controller
{
    public function store(Request $request, RunOccurrence $occurrence, RsvpService $service)
    {
        $rsvp = $service->reserve($request->user(), $occurrence);

        return back()->with('status', $rsvp->status === 'confirmed' ? 'Place confirmed.' : 'Added to waitlist.');
    }

    public function destroy(Request $request, RunOccurrence $occurrence, RsvpService $service)
    {
        $service->withdraw($request->user(), $occurrence);

        return back()->with('status', 'RSVP withdrawn.');
    }
}
