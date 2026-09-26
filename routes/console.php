<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Run;
use App\Models\RunOccurrence;
use App\Models\Rsvp;
use App\Notifications\RunNotice;
use App\Services\OccurrenceService;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pacekita:generate-occurrences', function (OccurrenceService $service) {
    Run::whereNotNull('recurrence_rule')->each(fn (Run $run) => $service->generate($run));
})->purpose('Extend weekly run occurrences eight weeks ahead');

Artisan::command('pacekita:send-reminders', function () {
    Rsvp::where('status', 'confirmed')->whereNull('reminder_sent_at')
        ->whereHas('occurrence', fn ($query) => $query->where('status', 'published')->whereBetween('start_at_utc', [now(), now()->addDay()]))
        ->with('occurrence.run', 'user')->chunkById(100, function ($rsvps) {
            foreach ($rsvps as $rsvp) {
                DB::transaction(function () use ($rsvp) {
                    $locked = Rsvp::lockForUpdate()->find($rsvp->id);
                    if (! $locked || $locked->reminder_sent_at || $locked->status !== 'confirmed') return;
                    $locked->update(['reminder_sent_at' => now()]);
                    $rsvp->user->notify(new RunNotice('Your run is coming up', $rsvp->occurrence->run->title.' starts in less than 24 hours.', $rsvp->occurrence_id));
                });
            }
        });
})->purpose('Queue one reminder for each upcoming confirmed runner');

Artisan::command('pacekita:complete-occurrences', function () {
    RunOccurrence::where('status', 'published')->where('start_at_utc', '<', now()->subDay())->update(['status' => 'completed']);
})->purpose('Close past occurrences');

Schedule::command('pacekita:generate-occurrences')->daily()->withoutOverlapping();
Schedule::command('pacekita:send-reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('pacekita:complete-occurrences')->daily()->withoutOverlapping();
