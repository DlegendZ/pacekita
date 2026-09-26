<?php

namespace App\Services;

use App\Models\Run;
use App\Models\RunOccurrence;
use Carbon\CarbonImmutable;

class OccurrenceService
{
    public function create(Run $run, array $data): RunOccurrence
    {
        $local = CarbonImmutable::parse($data['start_local'], $data['timezone']);
        $start = $local->utc();
        $cutoff = $start->subHours((int) $data['cutoff_hours']);
        $values = [
            'timezone' => $data['timezone'], 'duration_minutes' => $data['duration_minutes'],
            'distance_km' => $data['distance_km'], 'pace_min_seconds_per_km' => $data['pace_min_seconds_per_km'],
            'pace_max_seconds_per_km' => $data['pace_max_seconds_per_km'], 'meeting_area' => $data['meeting_area'],
            'safety_notes' => $data['safety_notes'] ?? null, 'capacity' => $data['capacity'],
            'visibility' => $data['visibility'], 'status' => 'draft',
        ];
        $occurrence = $run->occurrences()->create($values + ['start_at_utc' => $start, 'cutoff_at_utc' => $cutoff]);
        if (! empty($data['repeat_until'])) {
            $run->update(['recurrence_rule' => [
                'frequency' => 'weekly', 'start_local' => $local->format('Y-m-d H:i:s'),
                'timezone' => $data['timezone'], 'until' => $data['repeat_until'],
                'cutoff_hours' => (int) $data['cutoff_hours'],
            ]]);
            $this->generate($run);
        }
        return $occurrence;
    }

    public function generate(Run $run): void
    {
        $rule = $run->recurrence_rule;
        if (! $rule || ($rule['frequency'] ?? null) !== 'weekly') return;
        $local = CarbonImmutable::parse($rule['start_local'], $rule['timezone']);
        $limit = CarbonImmutable::parse($rule['until'], $rule['timezone'])->endOfDay();
        $horizon = CarbonImmutable::now($rule['timezone'])->addWeeks(8);
        $template = $run->occurrences()->oldest('start_at_utc')->firstOrFail();
        for ($next = $local->addWeek(); $next->lte($limit) && $next->lte($horizon); $next = $next->addWeek()) {
            if ($next->isPast()) continue;
            $start = $next->utc();
            $run->occurrences()->firstOrCreate(['start_at_utc' => $start], [
                'timezone' => $rule['timezone'], 'duration_minutes' => $template->duration_minutes,
                'distance_km' => $template->distance_km, 'pace_min_seconds_per_km' => $template->pace_min_seconds_per_km,
                'pace_max_seconds_per_km' => $template->pace_max_seconds_per_km, 'meeting_area' => $template->meeting_area,
                'safety_notes' => $template->safety_notes, 'capacity' => $template->capacity,
                'cutoff_at_utc' => $start->subHours($rule['cutoff_hours']),
                'visibility' => $template->visibility, 'status' => $template->status,
            ]);
        }
    }
}
