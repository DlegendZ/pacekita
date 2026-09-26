<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $fillable = ['display_name', 'city', 'photo_path', 'preferred_distance_km', 'preferred_pace_seconds_per_km', 'bio', 'emergency_contact'];

    protected function casts(): array { return ['emergency_contact' => 'encrypted']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
