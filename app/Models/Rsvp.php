<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rsvp extends Model
{
    protected $fillable = ['occurrence_id', 'user_id', 'status', 'waitlisted_at', 'confirmed_at', 'checked_in_at'];

    protected function casts(): array
    {
        return ['waitlisted_at' => 'datetime', 'confirmed_at' => 'datetime', 'checked_in_at' => 'datetime'];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(RunOccurrence::class, 'occurrence_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
