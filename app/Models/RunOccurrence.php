<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RunOccurrence extends Model
{
    protected $fillable = ['run_id', 'start_at_utc', 'timezone', 'duration_minutes', 'distance_km', 'pace_min_seconds_per_km', 'pace_max_seconds_per_km', 'meeting_area', 'safety_notes', 'capacity', 'cutoff_at_utc', 'visibility', 'status', 'cancellation_reason'];

    protected function casts(): array { return ['start_at_utc' => 'datetime', 'cutoff_at_utc' => 'datetime']; }

    public function run(): BelongsTo { return $this->belongsTo(Run::class); }
    public function rsvps(): HasMany { return $this->hasMany(Rsvp::class, 'occurrence_id'); }

    public function isOpen(): bool
    {
        return $this->status === 'published' && $this->start_at_utc->isFuture() && $this->cutoff_at_utc->isFuture();
    }
}
